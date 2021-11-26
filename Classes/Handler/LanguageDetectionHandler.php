<?php

declare(strict_types=1);

namespace LD\LanguageDetection\Handler;

use LD\LanguageDetection\Event\BuildResponse;
use LD\LanguageDetection\Event\CheckLanguageDetection;
use LD\LanguageDetection\Event\DetectUserLanguages;
use LD\LanguageDetection\Event\NegotiateSiteLanguage;
use LD\LanguageDetection\Handler\Exception\DisableLanguageDetectionException;
use LD\LanguageDetection\Handler\Exception\NoResponseException;
use LD\LanguageDetection\Handler\Exception\NoSelectedLanguageException;
use LD\LanguageDetection\Handler\Exception\NoUserLanguagesException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Core handler for handling the language detection, with all 4 core events.
 */
class LanguageDetectionHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->getSiteFromRequest($request);

        $check = new CheckLanguageDetection($site, $request);
        $this->eventDispatcher->dispatch($check);

        if (!$check->isLanguageDetectionEnable()) {
            throw new DisableLanguageDetectionException();
        }

        $detect = new DetectUserLanguages($site, $request);
        $this->eventDispatcher->dispatch($detect);

        if (empty($detect->getUserLanguages())) {
            throw new NoUserLanguagesException();
        }

        $negotiate = new NegotiateSiteLanguage($site, $request, $detect->getUserLanguages());
        $this->eventDispatcher->dispatch($negotiate);

        if (null === $negotiate->getSelectedLanguage()) {
            throw new NoSelectedLanguageException();
        }

        $response = new BuildResponse($site, $request, $negotiate->getSelectedLanguage());
        $this->eventDispatcher->dispatch($response);

        if (null === $response->getResponse()) {
            throw new NoResponseException();
        }

        return $response->getResponse();
    }
}
