<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ViewModels\Asset;

use Adeliom\HorizonTools\Services\Compilation\CompilationService;
use Adeliom\HorizonTools\Services\Traits\CompilatorServiceTrait;

class AssetViewModel
{
    use CompilatorServiceTrait;

    private readonly string $file;
    private bool $isVite = false;
    private bool $isBud = false;
    private ?string $url = null;
    private ?string $path = null;
    private ?string $type = null;
    private ?array $associatedAssets = null;
    private readonly bool $isFirstLevel;

    private const ASSET_TYPE_SCRIPT = 'script';
    private const ASSET_TYPE_STYLE = 'style';

    public const HANDLE_PREFIX_VITE = 'vite_';
    public const HANDLE_PREFIX_BUD = 'bud_';

    public function __construct(
        string $file,
        bool $firstLevel = true,
        private readonly ?string $forceUrl = null,
        private readonly bool $isHot = false
    ) {
        $this->file = $file;
        $this->isFirstLevel = $firstLevel;

        $this->setIsVite()->setIsBud()->setUrl()->setPath()->setType()->setAssociatedAssets();
    }

    public function isVite(): bool
    {
        return $this->isVite;
    }

    public function setIsVite(?bool $isVite = null): self
    {
        if (null === $isVite) {
            $isVite = CompilationService::shouldUseVite();
        }

        $this->isVite = $isVite;

        return $this;
    }

    public function isBud(): bool
    {
        return $this->isBud;
    }

    public function setIsBud(?bool $isBud = null): self
    {
        if (null === $isBud) {
            $isBud = CompilationService::shouldUseBud();
        }

        $this->isBud = $isBud;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url = null): self
    {
        if ($this->forceUrl) {
            $url = $this->forceUrl;
        } else {
            if (null === $url) {
                $url = sprintf('%s%s%s', get_template_directory_uri(), self::getBuildDirectory(full: false), $this->file ?? '');
            }
        }

        $this->url = $url;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path = null): self
    {
        if (null === $path) {
            if ($this->forceUrl) {
                $path = sprintf('%s%s', get_template_directory(), $this->file ?? '');
            } else {
                $path = sprintf('%s%s', self::getBuildDirectory(), $this->file ?? '');
            }
        }

        $this->path = $path;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type = null): self
    {
        if (null === $type) {
            if ($this->endsWithOneOf($this->file, ['.js', '.ts'])) {
                $type = self::ASSET_TYPE_SCRIPT;
            } elseif ($this->endsWithOneOf($this->file, ['.css'])) {
                $type = self::ASSET_TYPE_STYLE;
            }
        }

        $this->type = $type;

        return $this;
    }

    public function isScript(): bool
    {
        return $this->getType() === self::ASSET_TYPE_SCRIPT;
    }

    public function isStyle(): bool
    {
        return $this->getType() === self::ASSET_TYPE_STYLE;
    }

    /**
     * @return AssetViewModel[]|null
     */
    public function getAssociatedAssets(): ?array
    {
        return $this->associatedAssets;
    }

    public function setAssociatedAssets(?array $associatedAssets = null): self
    {
        if (null === $associatedAssets && $this->isFirstLevel) {
            $manifest = self::getManifest();

            foreach ($manifest as $item) {
                if (!empty($item['file']) && $item['file'] === $this->file) {
                    if (!empty($item['css'])) {
                        foreach ($item['css'] as $css) {
                            if ($cssAsset = new self(file: $css, firstLevel: false)) {
                                $associatedAssets[] = $cssAsset;
                            }
                        }
                    }
                }
            }
        }

        $this->associatedAssets = $associatedAssets;

        return $this;
    }

    public function enqueue(
        array $dependencies = [],
        ?string $version = null,
        bool $args = true,
        ?AssetViewModel $instance = null
    ): AssetViewModel {
        if (null === $instance) {
            $instance = $this;
        }

        $handle = $instance->file;

        if ($this->isHot) {
            $handlePrefix = '';

            switch (true) {
                case $this->isVite():
                    $handlePrefix = self::HANDLE_PREFIX_VITE . $handlePrefix;
                    break;
                case $this->isBud():
                    $handlePrefix = self::HANDLE_PREFIX_BUD . $handlePrefix;
                    break;
            }

            $handle = $handlePrefix . $handle;
        }

        if ($instance->isScript()) {
            wp_enqueue_script($handle, $instance->getUrl(), $dependencies, $version, $args);
        } elseif ($instance->isStyle()) {
            wp_enqueue_style($handle, $instance->getUrl());
        }

        return $instance;
    }

    public function enqueueAll(array $dependencies = [], ?string $version = null, bool $args = true): void
    {
        $this->enqueue(dependencies: $dependencies, version: $version, args: $args);

        if ($this->getAssociatedAssets()) {
            foreach ($this->getAssociatedAssets() as $associatedAsset) {
                $this->enqueue(dependencies: $dependencies, version: $version, args: $args, instance: $associatedAsset);
            }
        }
    }

    private function endsWithOneOf(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
