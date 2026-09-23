<?php

declare(strict_types=1);

namespace Ramblers\Component\YourComponent\Administrator\Service;

use RuntimeException;

/**
 * Small wrapper around the cPanel UAPI email functions.
 *
 * Designed to be injected into a Joomla 6 component.
 *
 * The API token is deliberately supplied to the constructor rather
 * than read from Joomla configuration here.
 */
final class CpanelEmailService {

    private string $host;
    private string $username;
    private string $apiToken;
    private int $port;

    /**
     * @param string $host      cPanel hostname, e.g. cpanel.example.org
     * @param string $username  cPanel account username
     * @param string $apiToken  cPanel API token
     * @param int    $port      Normally 2083
     */
    public function __construct(
            string $host,
            string $username,
            string $apiToken,
            int $port = 2083
    ) {
        $this->host = trim($host);
        $this->username = trim($username);
        $this->apiToken = trim($apiToken);
        $this->port = $port;

        if ($this->host === '') {
            throw new RuntimeException('cPanel host has not been supplied.');
        }

        if ($this->username === '') {
            throw new RuntimeException('cPanel username has not been supplied.');
        }

        if ($this->apiToken === '') {
            throw new RuntimeException('cPanel API token has not been supplied.');
        }
    }

    /**
     * FUNCTION 1
     *
     * Create a mailbox such as:
     *
     *     secretary@ramblers.tools
     *
     * cPanel requires a password even if the mailbox itself will
     * never be directly accessed, so one is generated automatically
     * unless supplied.
     */
    public function createEmailAccount(
            string $post,
            string $domain,
            ?string $password = null
    ): array {
        $post = $this->normalisePostName($post);
        $domain = $this->normaliseDomain($domain);

        $password ??= $this->generatePassword();

        $result = $this->call(
                'Email',
                'add_pop',
                [
                    'email' => $post,
                    'domain' => $domain,
                    'password' => $password,
                ]
        );

        return [
            'email' => $post . '@' . $domain,
            'created' => true,
            // Remove this from the return value in production if
            // nobody should ever know/use the mailbox password.
            'password' => $password,
            'cpanel' => $result,
        ];
    }

    /**
     * FUNCTION 2
     *
     * Retrieve email accounts and associate any forwarders with them.
     *
     * If $domain is supplied, only that domain is returned.
     *
     * Example result:
     *
     * [
     *   [
     *      'email' => 'secretary@ramblers.tools',
     *      'domain' => 'ramblers.tools',
     *      'forwarders' => [
     *          'fred@example.org'
     *      ]
     *   ]
     * ]
     */
    public function getEmailAccountsWithForwarders(
            ?string $domain = null
    ): array {
        if ($domain !== null) {
            $domain = $this->normaliseDomain($domain);
        }

        /*
         * list_pops returns the cPanel account's mailboxes.
         */
        $accountResponse = $this->call(
                'Email',
                'list_pops',
                [
                    'skip_main' => 1,
                ]
        );

        $accounts = $accountResponse['data'] ?? [];

        /*
         * Work out which domains need their forwarders retrieved.
         */
        $domains = [];

        foreach ($accounts as $account) {
            $emailAddress = $this->extractAccountEmail($account);

            if ($emailAddress === null) {
                continue;
            }

            [, $accountDomain] = explode('@', $emailAddress, 2);

            if ($domain !== null && $accountDomain !== $domain) {
                continue;
            }

            $domains[$accountDomain] = true;
        }

        /*
         * If a domain was specifically requested we should query
         * its forwarders even if there are currently no mailboxes.
         */
        if ($domain !== null) {
            $domains[$domain] = true;
        }

        $forwarderMap = [];

        foreach (array_keys($domains) as $mailDomain) {
            $forwardResponse = $this->call(
                    'Email',
                    'list_forwarders',
                    [
                        'domain' => $mailDomain,
                    ]
            );

            foreach (($forwardResponse['data'] ?? []) as $forwarder) {
                $source = $this->extractForwarderSource($forwarder);
                $destination = $this->extractForwarderDestination($forwarder);

                if ($source === null || $destination === null) {
                    continue;
                }

                $forwarderMap[$source] ??= [];
                $forwarderMap[$source][] = $destination;
            }
        }

        $result = [];

        foreach ($accounts as $account) {
            $emailAddress = $this->extractAccountEmail($account);

            if ($emailAddress === null) {
                continue;
            }

            [, $accountDomain] = explode('@', $emailAddress, 2);

            if ($domain !== null && $accountDomain !== $domain) {
                continue;
            }

            $result[] = [
                'email' => $emailAddress,
                'domain' => $accountDomain,
                'forwarders' => array_values(
                        array_unique($forwarderMap[$emailAddress] ?? [])
                ),
                // Keep the raw cPanel information available in case
                // Joomla needs quota/disk/etc later.
                'account' => $account,
            ];
        }

        return $result;
    }

    /**
     * FUNCTION 3
     *
     * Create a forwarder:
     *
     * secretary@ramblers.tools
     *          ->
     * new.postholder@example.org
     */
    public function createForwarder(
            string $organisationalEmail,
            string $personalEmail
    ): array {
        $organisationalEmail = $this->validateEmail($organisationalEmail);

        $personalEmail = $this->validateEmail($personalEmail);

        [, $domain] = explode('@', $organisationalEmail, 2);

        return $this->call(
                        'Email',
                        'add_forwarder',
                        [
                            'domain' => $domain,
                            'email' => $organisationalEmail,
                            'fwdopt' => 'fwd',
                            'fwdemail' => $personalEmail,
                        ]
        );
    }

    /**
     * FUNCTION 4
     *
     * Delete one specific forwarder.
     *
     * Both addresses are supplied because an organisational address
     * can theoretically have more than one forward destination.
     */
    public function deleteForwarder(
            string $organisationalEmail,
            string $personalEmail
    ): array {
        $organisationalEmail = $this->validateEmail($organisationalEmail);

        $personalEmail = $this->validateEmail($personalEmail);

        return $this->call(
                        'Email',
                        'delete_forwarder',
                        [
                            'email' => $organisationalEmail,
                            'emaildest' => $personalEmail,
                        ]
        );
    }

    /**
     * Convenience method for a postholder change.
     *
     * This isn't one of the four required functions, but will
     * probably become the operation the Joomla UI uses most often.
     */
    public function replaceForwarder(
            string $organisationalEmail,
            string $oldPersonalEmail,
            string $newPersonalEmail
    ): array {
        $this->deleteForwarder(
                $organisationalEmail,
                $oldPersonalEmail
        );

        return $this->createForwarder(
                        $organisationalEmail,
                        $newPersonalEmail
        );
    }

    /**
     * Perform one cPanel UAPI request.
     */
    private function call(
            string $module,
            string $function,
            array $parameters = []
    ): array {
        $url = sprintf(
                'https://%s:%d/execute/%s/%s',
                $this->host,
                $this->port,
                rawurlencode($module),
                rawurlencode($function)
        );

        if ($parameters !== []) {
            $url .= '?' . http_build_query(
                            $parameters,
                            '',
                            '&',
                            PHP_QUERY_RFC3986
            );
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException(
                            'Unable to initialise cURL.'
            );
        }

        curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 30,
                    /*
                     * Do not disable SSL verification in production.
                     */
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER => [
                        sprintf(
                                'Authorization: cpanel %s:%s',
                                $this->username,
                                $this->apiToken
                        ),
                        'Accept: application/json',
                    ],
                ]
        );

        $body = curl_exec($curl);

        if ($body === false) {
            $message = curl_error($curl);
            curl_close($curl);

            throw new RuntimeException(
                            'cPanel connection failed: ' . $message
            );
        }

        $httpStatus = curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException(
                            sprintf(
                                    'cPanel returned HTTP status %d.',
                                    $httpStatus
                            )
            );
        }

        try {
            $response = json_decode(
                    $body,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new RuntimeException(
                            'cPanel returned invalid JSON.',
                            0,
                            $e
            );
        }

        $result = $response['result'] ?? null;

        if (!is_array($result)) {
            throw new RuntimeException(
                            'Unexpected response from cPanel.'
            );
        }

        if (($result['status'] ?? 0) !== 1) {
            $errors = $result['errors'] ?? [
                'Unknown cPanel error'
            ];

            if (!is_array($errors)) {
                $errors = [$errors];
            }

            throw new RuntimeException(
                            'cPanel API error: '
                            . implode('; ', $errors)
            );
        }

        return $result;
    }

    /**
     * Validate an email address.
     */
    private function validateEmail(string $email): string {
        $email = strtolower(trim($email));

        if (
                filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                ) === false
        ) {
            throw new RuntimeException(
                            'Invalid email address: ' . $email
            );
        }

        return $email;
    }

    /**
     * Normalise a post name into the mailbox local part.
     *
     * "Membership Secretary" becomes "membership-secretary".
     */
    private function normalisePostName(string $post): string {
        $post = strtolower(trim($post));

        $post = preg_replace(
                '/[^a-z0-9._-]+/',
                '-',
                $post
        );

        $post = trim((string) $post, '.-_');

        if (
                $post === '' || !preg_match(
                        '/^[a-z0-9][a-z0-9._-]*$/',
                        $post
                )
        ) {
            throw new RuntimeException(
                            'Invalid post name.'
            );
        }

        return $post;
    }

    private function normaliseDomain(string $domain): string {
        $domain = strtolower(trim($domain));

        /*
         * Validate by constructing a temporary email address.
         */
        if (
                filter_var(
                        'test@' . $domain,
                        FILTER_VALIDATE_EMAIL
                ) === false
        ) {
            throw new RuntimeException(
                            'Invalid domain: ' . $domain
            );
        }

        return $domain;
    }

    /**
     * Generate a password for an otherwise unused mailbox.
     */
    private function generatePassword(): string {
        return bin2hex(random_bytes(20));
    }

    /**
     * Different cPanel releases/functions sometimes expose several
     * representations of an account address, so keep the conversion
     * isolated here.
     */
    private function extractAccountEmail(array $account): ?string {
        if (
                !empty($account['email']) && str_contains($account['email'], '@')
        ) {
            return strtolower($account['email']);
        }

        if (
                !empty($account['user']) && !empty($account['domain'])
        ) {
            return strtolower(
                    $account['user']
                    . '@'
                    . $account['domain']
            );
        }

        if (
                !empty($account['login']) && str_contains($account['login'], '@')
        ) {
            return strtolower($account['login']);
        }

        return null;
    }

    private function extractForwarderSource(
            array $forwarder
    ): ?string {
        foreach (
                ['email', 'address', 'forward']
        as $key
        ) {
            if (
                    isset($forwarder[$key]) && filter_var(
                            $forwarder[$key],
                            FILTER_VALIDATE_EMAIL
                    )
            ) {
                return strtolower(
                        $forwarder[$key]
                );
            }
        }

        return null;
    }

    private function extractForwarderDestination(
            array $forwarder
    ): ?string {
        foreach (
                ['dest', 'destination', 'emaildest']
        as $key
        ) {
            if (
                    isset($forwarder[$key]) && filter_var(
                            $forwarder[$key],
                            FILTER_VALIDATE_EMAIL
                    )
            ) {
                return strtolower(
                        $forwarder[$key]
                );
            }
        }

        return null;
    }

}
