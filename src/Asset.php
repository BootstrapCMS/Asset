<?php

namespace Lightgear\Asset;

use Illuminate\Html\HtmlFacade as HTML;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JSMin;
use Minify_CSS;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Asset
{
    protected $paths = [];

    protected $styles = [];

    protected $scripts = [];

    protected $processed = [
        'styles'  => [],
        'scripts' => [],
    ];

    /**
     * Register a styles collection.
     *
     * @param array  $assets
     * @param string $package
     * @param string $group
     *
     * @return \Lightgear\Asset\Asset
     */
    public function registerStyles($assets, $package = '', $group = 'general')
    {
        $this->registerAssets($assets, 'styles', $package, $group);

        return $this;
    }

    /**
     * Register a scripts collection.
     *
     * @param array  $assets
     * @param string $package
     * @param string $group
     *
     * @return \Lightgear\Asset\Asset
     */
    public function registerScripts($assets, $package = '', $group = 'general')
    {
        $this->registerAssets($assets, 'scripts', $package, $group);

        return $this;
    }

    /**
     * Render styles assets.
     *
     * @param string|string[] $groups
     *
     * @return void
     */
    public function styles($groups = [])
    {
        return $this->renderAssets($groups, 'styles');
    }

    /**
     * Render scripts assets.
     *
     * @param string|string[] $groups
     *
     * @return void
     */
    public function scripts($groups = [])
    {
        return $this->renderAssets($groups, 'scripts');
    }

    /**
     * Adds a new search path.
     *
     * @param string $path
     *
     * @return void
     */
    public function addPath($path)
    {
        $this->paths[] = $path;
    }

    /**
     * Delete published assets.
     *
     * @return void
     */
    public function clean()
    {
        $assetsDir = public_path().'/'.Config::get('asset.public_dir');

        foreach (array_keys($this->processed) as $typeDir) {
            File::deleteDirectory($assetsDir.'/'.$typeDir);
        }

        foreach ($this->getGroupNames('styles') as $group) {
            Cache::forget('asset.styles.groups.'.$group);
        }

        foreach ($this->getGroupNames('scripts') as $group) {
            Cache::forget('asset.scripts.groups.'.$group);
        }
    }

    /**
     * Render the assets.
     *
     * @param string|string[] $groups
     * @param string          $type
     *
     * @return string
     */
    protected function renderAssets($groups, $type)
    {
        $groups = (array) $groups;

        if (empty($groups)) {
            $groups = $this->getGroupNames($type);
        }

        $output = '';

        foreach ($groups as $group) {
            // use cached resources, if available
            $cacheKey = 'asset.'.$type.'.groups.'.$group;
            if (Config::get('asset.use_cache') && Cache::has($cacheKey)) {
                $output .= Cache::get($cacheKey);
            } elseif (array_key_exists($group, $this->{$type})) {
                $this->processAssets($this->{$type}[$group], $group);
                $output .= $this->publish($type, $group);
            }
        }

        return $output;
    }

    /**
     * Register an asset collection.
     *
     * @param array  $assets
     * @param string $type
     * @param string $package
     * @param string $group
     *
     * @return void
     */
    protected function registerAssets($assets, $type, $package, $group)
    {
        if (!isset($this->{$type}[$group][$package])) {
            $this->{$type}[$group][$package] = [];
        }

        $this->{$type}[$group][$package] = array_unique(array_merge($this->{$type}[$group][$package], $assets));
    }

    /**
     * Process an assets collection.
     *
     * @param array  $assets
     * @param string $group
     *
     * @return void
     */
    protected function processAssets($assets, $group)
    {
        foreach ($assets as $package => $paths) {
            foreach ($paths as $path) {
                $files = $this->findAssets($path, $package);

                // skip not found assets
                if (!$files) {
                    continue;
                }

                foreach ($files as $file) {
                    $assetData = ['is_minified' => $this->isMinified($file->getRealPath())];

                    switch ($file->getExtension()) {
                        case 'css':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['styles'][$group][] = $assetData;
                            break;
                        case 'js':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'scripts');
                            $this->processed['scripts'][$group][] = $assetData;
                            break;
                    }
                }
            }
        }
    }

    /**
     * Search for the assets in the passed path.
     *
     * @param string $path
     * @param string $package
     *
     * @return \Symfony\Component\Finder\SplFileInfo[]|null
     */
    protected function findAssets($path, $package)
    {
        $paths = array_merge($this->paths, Config::get('asset.search_paths'));

        foreach ($paths as $searchPath) {
            $fullPath = base_path().$searchPath.'/'.$package.'/'.$path;

            if (File::isDirectory($fullPath)) {
                return File::allFiles($fullPath);
            }

            if (File::isFile($fullPath)) {
                return Finder::create()->depth(0)->name(basename($fullPath))->in(dirname($fullPath));
            }
        }
    }

    /**
     * Builds the target paths array.
     *
     * The 'link' ready to be used as asset url and 'full' suitable for file creation.
     *
     * @param \Symfony\Component\Finder\SplFileInfo|string $file
     * @param string                                       $package
     * @param string                                       $type
     *
     * @return array
     */
    protected function buildTargetPaths($file, $package, $type)
    {
        if ($file instanceof SplFileInfo) {
            $pathName = $file->getRelativePathname();
        } else {
            $pathName = $file;
        }

        // replace .less extension by .css
        $pathName = str_ireplace('.less', '.css', $pathName);

        $link = '/'.Config::get('asset.public_dir').'/'.$type.'/';

        // add package segment, if any
        if ($package) {
            $link .= $package.'/';
        }

        $link .= $pathName;

        return ['link' => $link, 'full' => public_path().$link];
    }

    /**
     * Publish a collection of processed assets.
     *
     * @param string $type
     * @param string $group
     *
     * @return string
     */
    protected function publish($type, $group)
    {
        $output = '';
        $minify = Config::get('asset.minify');
        $useCache = Config::get('asset.use_cache');

        // no assets to publish, stop here!
        if (!isset($this->processed[$type][$group])) {
            return;
        }

        foreach ($this->processed[$type][$group] as $asset) {
            // minify, if the asset isn't yet
            if ($minify && !$asset['is_minified']) {
                $asset['contents'] = $this->minifyAsset($asset['contents'], $type);
            }

            $output .= $this->publishAsset($asset, $type);
        }

        // cache asset resource
        $cacheKey = 'asset.'.$type.'.groups.'.$group;

        if ($useCache) {
            Cache::forever($cacheKey, $output);
        } elseif (Cache::has($cacheKey)) {
            $this->clean();
        }

        return $output;
    }

    /**
     * Publish a single asset.
     *
     * @param array  $asset
     * @param string $type
     *
     * @return string
     */
    protected function publishAsset($asset, $type)
    {
        $output = '';

        // prepare target directory
        if (!file_exists(dirname($asset['full']))) {
            File::makeDirectory(dirname($asset['full']), 0777, true);
        }

        // create the asset file
        File::put($asset['full'], $asset['contents']);

        // add the element
        $link = $asset['link'].'?'.str_random(10);
        if ($type === 'styles') {
            $output .= HTML::style($link);
        } elseif ($type === 'scripts') {
            $output .= HTML::script($link);
        }

        return $output;
    }

    /**
     * Minifies asset contents.
     *
     * @param string $contents
     * @param string $type
     *
     * @return string
     */
    protected function minifyAsset($contents, $type)
    {
        if ($type === 'styles') {
            return Minify_CSS::minify($contents, ['preserveComments' => false]);
        }

        if ($type === 'scripts') {
            return JSMin::minify($contents);
        }
    }

    /**
     * Check if an asset is already minified.
     *
     * @param string $fullpath
     *
     * @return bool
     */
    protected function isMinified($fullpath)
    {
        $filename = basename($fullpath);

        foreach (Config::get('asset.minify_patterns') as $pattern) {
            if (Str::contains($filename, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the registered groups for a specific type.
     *
     * @param string $type
     *
     * @return array
     */
    protected function getGroupNames($type)
    {
        return array_keys($this->{$type});
    }
}
