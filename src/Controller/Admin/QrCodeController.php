<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Admin\QrCodeAdmin;
use Islesurlasorguetourisme\SuluQrCodeBundle\Api\ListFactory;
use Islesurlasorguetourisme\SuluQrCodeBundle\Api\QrCodeNormalizer;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\InvalidQrCodeDataException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\UnsupportedImageSizeException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\QrCodeImageManager;
use Islesurlasorguetourisme\SuluQrCodeBundle\Mapper\QrCodeDataMapper;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;
use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepositoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Administration API of the QR codes.
 *
 * Sulu checks the permission matching the HTTP method (view, add, edit, delete)
 * against {@see QrCodeController::getSecurityContext()} before any action runs.
 */
final class QrCodeController implements SecuredControllerInterface
{
    public function __construct(
        private readonly QrCodeRepositoryInterface $repository,
        private readonly QrCodeDataMapper $mapper,
        private readonly QrCodeNormalizer $normalizer,
        private readonly QrCodeImageManager $imageManager,
        private readonly ListFactory $listFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function cgetAction(): Response
    {
        $list = $this->listFactory->create(QrCode::class, QrCodeAdmin::LIST_KEY);
        // Required by the "tagNames" column, which aggregates the tags of each QR code.
        $list->listBuilder->addGroupBy($list->field('id'));

        return new JsonResponse($list->toRepresentation(
            $this->addComputedValues($list->getRows()),
            QrCode::RESOURCE_KEY,
        ));
    }

    public function getAction(string $id): Response
    {
        return new JsonResponse($this->normalizer->normalize($this->findOr404($id), $this->getUserLocale()));
    }

    public function postAction(Request $request): Response
    {
        try {
            $qrCode = $this->mapper->create($this->getData($request));
        } catch (InvalidQrCodeDataException $exception) {
            return $this->createValidationErrorResponse($exception);
        }

        $this->repository->add($qrCode);
        $this->repository->flush();

        return new JsonResponse($this->normalizer->normalize($qrCode, $this->getUserLocale()), Response::HTTP_CREATED);
    }

    public function putAction(string $id, Request $request): Response
    {
        $qrCode = $this->findOr404($id);

        try {
            $this->mapper->update($qrCode, $this->getData($request));
        } catch (InvalidQrCodeDataException $exception) {
            // Do not leave a half-modified entity in the unit of work.
            $this->entityManager->refresh($qrCode);

            return $this->createValidationErrorResponse($exception);
        }

        $this->repository->flush();
        // Files rendered with the previous options will never be requested again.
        $this->imageManager->purge($qrCode);

        return new JsonResponse($this->normalizer->normalize($qrCode, $this->getUserLocale()));
    }

    public function deleteAction(string $id): Response
    {
        $this->delete($this->findOr404($id));
        $this->repository->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function cdeleteAction(Request $request): Response
    {
        $ids = \array_filter(\explode(',', $request->query->getString('ids')));

        foreach ($ids as $id) {
            $qrCode = $this->repository->findById($id);
            if (null !== $qrCode) {
                $this->delete($qrCode);
            }
        }

        $this->repository->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Lists the files that can be downloaded for a QR code.
     */
    public function filesAction(string $qrCodeId): Response
    {
        $files = $this->normalizer->normalizeFiles($this->findOr404($qrCodeId), $this->getUserLocale());

        return new JsonResponse((new PaginatedRepresentation(
            $files,
            QrCodeAdmin::FILES_RESOURCE_KEY,
            1,
            \count($files),
            \count($files),
        ))->toArray());
    }

    /**
     * Serves the image of a QR code, inline (thumbnails) or as a download.
     */
    public function imageAction(string $id, string $imageFormat, Request $request): Response
    {
        $qrCode = $this->findOr404($id);
        $format = ImageFormat::tryFrom($imageFormat) ?? throw new NotFoundHttpException();
        $size = $request->query->has('size') ? $request->query->getInt('size') : null;

        try {
            $image = $this->imageManager->getImage($qrCode, $format, $size);
        } catch (UnsupportedImageSizeException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $response = new Response($image->content, Response::HTTP_OK, [
            'Content-Type' => $format->mimeType(),
            'X-Content-Type-Options' => 'nosniff',
            // The SVG is served from the administration origin: forbid anything but drawing.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
        $response->setPrivate();

        if ($request->query->getBoolean('download')) {
            $slug = (new AsciiSlugger())->slug($qrCode->getTitle())->lower()->truncate(60)->toString();
            $fileName = \sprintf('qr-%s-%s.%s', '' !== $slug ? $slug : 'code', $qrCode->getCode(), $format->value);

            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName));
            $response->headers->addCacheControlDirective('no-store');

            return $response;
        }

        // Thumbnail URLs carry the fingerprint ("v"): when the image changes, so does its URL.
        $response->setEtag($image->fingerprint);
        $response->setMaxAge($request->query->has('v') ? 2592000 : 0);
        $response->isNotModified($request);

        return $response;
    }

    public function getSecurityContext(): string
    {
        return QrCodeAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return $request->query->get('locale');
    }

    private function delete(QrCode $qrCode): void
    {
        $this->imageManager->purge($qrCode);
        $this->repository->remove($qrCode);
    }

    private function findOr404(string $id): QrCode
    {
        return $this->repository->findById($id) ?? throw new NotFoundHttpException(\sprintf('QR code "%s" not found.', $id));
    }

    /**
     * The list builder only returns the columns it was asked for. Everything derived
     * (thumbnail, target, download links) is computed from the entities of the page.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function addComputedValues(array $rows): array
    {
        if ([] === $rows) {
            return $rows;
        }

        $qrCodes = [];
        foreach ($this->entityManager->getRepository(QrCode::class)->findBy(['id' => \array_column($rows, 'id')]) as $qrCode) {
            $qrCodes[$qrCode->getId()] = $qrCode;
        }

        $locale = $this->getUserLocale();

        foreach ($rows as $index => $row) {
            $qrCode = $qrCodes[$row['id']] ?? null;
            if (null !== $qrCode) {
                $rows[$index] = [...$row, ...$this->normalizer->normalizeComputed($qrCode, $locale)];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(Request $request): array
    {
        $data = $request->request->all();

        if ([] !== $data || '' === $request->getContent()) {
            return $data;
        }

        // Not decoded by a body listener: read the JSON payload ourselves.
        $decoded = [];
        foreach ($request->toArray() as $key => $value) {
            $decoded[(string) $key] = $value;
        }

        return $decoded;
    }

    private function createValidationErrorResponse(InvalidQrCodeDataException $exception): JsonResponse
    {
        $message = $this->translator->trans(
            $exception->getTranslationKey(),
            $exception->getTranslationParameters(),
            'admin',
            $this->getUserLocale(),
        );

        // Sulu's form view displays the "detail" of a failed save to the editor.
        return new JsonResponse([
            'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'message' => $message,
            'detail' => $message,
            'field' => $exception->getField(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function getUserLocale(): ?string
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof UserInterface ? $user->getLocale() : null;
    }
}
