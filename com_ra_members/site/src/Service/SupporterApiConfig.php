<?php

/*
 * 22/08/26 Created by chatGPT
*/

namespace Ramblers\Component\Ra_members\Site\Service;

defined('JPATH_PLATFORM') or die;

/**
 * Validated, secret-safe configuration for the supporter feed.
 */
final class SupporterApiConfig {

    private const SUB_SYSTEM = 'RA Members';

    private $id;
    private $url;
    private $token;

    private function __construct(int $id, string $url, string $token) {
        $this->id = $id;
        $this->url = $url;
        $this->token = $token;
    }

    public static function fromRow($row, int $requestedId): self {
        if ($requestedId < 1) {
            throw new \InvalidArgumentException('A positive API site ID is required.');
        }

        if (!is_object($row) || (int) ($row->id ?? 0) !== $requestedId) {
            throw new \RuntimeException('API site ID ' . $requestedId . ' was not found.');
        }

        if ((int) ($row->state ?? 0) !== 1) {
            throw new \RuntimeException('API site ID ' . $requestedId . ' is not enabled.');
        }

        if (strcasecmp(trim((string) ($row->sub_system ?? '')), self::SUB_SYSTEM) !== 0) {
            throw new \RuntimeException('API site ID ' . $requestedId . ' is not configured for RA Members.');
        }

        $url = trim((string) ($row->url ?? ''));
        $urlParts = parse_url($url);

        if ($url === '' || $urlParts === false
                || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
                || empty($urlParts['host'])) {
            throw new \RuntimeException('API site ID ' . $requestedId . ' must have a valid HTTPS URL.');
        }

        $token = trim((string) ($row->token ?? ''));

        if ($token === '') {
            throw new \RuntimeException('API site ID ' . $requestedId . ' has no API key.');
        }

        return new self($requestedId, $url, $token);
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function getToken(): string {
        return $this->token;
    }
}
