<?php

declare(strict_types=1);

namespace SensioLabs\Deptrac\AstRunner\AstParser;

use SensioLabs\Deptrac\AstRunner\AstMap\AstClassReference;
use SensioLabs\Deptrac\AstRunner\AstMap\AstDependency;
use SensioLabs\Deptrac\AstRunner\AstMap\AstFileReference;
use SensioLabs\Deptrac\AstRunner\AstMap\AstInherit;
use SensioLabs\Deptrac\Console\Application;

class AstFileReferenceFileCache implements AstFileReferenceCacheInterface
{
    private $cache;
    private $cacheFile;
    private $loaded = false;
    private $parsedFiles = [];

    public function __construct()
    {
        $this->cache = [];
        $this->cacheFile = getcwd().'/.deptrac.cache';
    }

    public function has(string $filepath): bool
    {
        $this->load();

        $filepath = realpath($filepath);

        if (!isset($this->cache[$filepath])) {
            return false;
        }

        $hash = sha1_file($filepath);

        if ($hash !== $this->cache[$filepath]['hash']) {
            unset($this->cache[$filepath]);

            return false;
        }

        return true;
    }

    public function get(string $filepath): ?AstFileReference
    {
        $this->load();

        $filepath = realpath($filepath);

        if ($this->has($filepath)) {
            $this->parsedFiles[$filepath] = true;

            return $this->cache[$filepath]['reference'];
        }

        return null;
    }

    public function set(AstFileReference $fileReference): void
    {
        $this->load();

        $filepath = realpath($fileReference->getFilepath());

        $this->parsedFiles[$filepath] = true;

        $this->cache[$filepath] = [
            'hash' => sha1_file($filepath),
            'reference' => $fileReference,
        ];
    }

    public function load(): void
    {
        if (true === $this->loaded) {
            return;
        }

        if (!file_exists($this->cacheFile) || !is_readable($this->cacheFile)) {
            return;
        }

        $cache = json_decode(file_get_contents($this->cacheFile), true);

        $this->loaded = true;

        if (Application::VERSION !== $cache['version']) {
            return;
        }

        $this->cache = array_map(
            function (array $data) {
                $data['reference'] = unserialize($data['reference'], [
                    'allowed_classes' => [
                        AstFileReference::class,
                        AstClassReference::class,
                        AstInherit::class,
                        AstDependency::class,
                    ],
                ]);

                return $data;
            },
            $cache['payload']
        );
    }

    public function write(): void
    {
        if (!is_writable(\dirname($this->cacheFile))) {
            return;
        }

        $cache = array_filter(
            $this->cache,
            function ($key) {
                return isset($this->parsedFiles[$key]);
            },
            ARRAY_FILTER_USE_KEY
        );

        $payload = array_map(
            function (array $data) {
                $data['reference'] = serialize($data['reference']);

                return $data;
            },
            $cache
        );

        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'version' => Application::VERSION,
                    'payload' => $payload,
                ]
            )
        );
    }
}
