<?php

namespace Statamic\Migrator;

use Statamic\Facades\YAML;
use Statamic\Support\Str;

class PagesMigrator extends Migrator
{
    protected $root;
    protected $structure = [];
    protected $entries = [];
    protected $blueprints = ['page'];

    /**
     * Migrate file.
     *
     * @param string $handle
     */
    public function migrate($handle)
    {
        $this->newPath = base_path("content/collections/pages");

        $this
            ->copySourceFiles()
            ->parseTree()
            ->createStructure()
            ->createYamlConfig()
            ->migratePagesToEntries();
    }

    /**
     * Copy source files.
     *
     * return $this
     */
    protected function copySourceFiles()
    {
        if ($this->sourcePath === $this->newPath) {
            return $this;
        }

        $this->files->copyDirectory($this->sourcePath, $this->newPath);

        return $this;
    }

    /**
     * Parse tree.
     *
     * @return this
     */
    protected function parseTree()
    {
        $this->parsePageFolder($this->newPath());

        return $this;
    }

    /**
     * Parse page folder.
     *
     * @param string $folder
     * @param string $key
     * @return array
     */
    protected function parsePageFolder($folder, $key = 'tree')
    {
        $this->entries[] = $page = YAML::parse($this->files->get("{$folder}/index.md"));

        $entry = $page['id'];

        $children = collect($this->files->directories("$folder"))
            ->map(function ($folder) use ($key, $entry) {
                return $this->parsePageFolder($folder, "{$key}.{$entry}");
            })
            ->all();

        $data = collect(compact('entry', 'children'))->filter()->all();

        data_set($this->structure, $key, $data);

        return $data;
    }

    /**
     * Create structure.
     *
     * @return $this
     */
    protected function createStructure()
    {
        $tree = $this->structure['tree'];

        $config = [
            'title' => 'Pages',
            'expects_root' => true,
            'root' => $this->root,
            'tree' => array_values($this->structure),
        ];

        $this->files->put($this->newPath('../../structures/pages.yaml'), YAML::dump($config));

        return $this;
    }

    /**
     * Create yaml config.
     *
     * @return $this
     */
    protected function createYamlConfig()
    {
        $config = [
            'title' => 'Pages',
            'route' => '{{ parent_uri }}/{{ slug }}',
            'blueprints' => $this->blueprints,
            'structure' => 'pages',
        ];

        $this->files->put($this->newPath('../pages.yaml'), YAML::dump($config));

        return $this;
    }

    /**
     * Migrate pages to entries.
     *
     * @return $this
     */
    protected function migratePagesToEntries()
    {
        $this->files->cleanDirectory($this->newPath());

        collect($this->entries)
            ->map(function ($entry) {
                return $this->migrateFieldsetToBlueprint($entry);
            })
            ->map(function ($entry) {
                $this->files->put($this->newPath(Str::slug($entry['title'])).'.md', $this->dumpEntryToMarkdown($entry));
            });

        return $this;
    }


    /**
     * Migrate fieldset to blueprint.
     *
     * @param array $entry
     * @return array
     */
    protected function migrateFieldsetToBlueprint($entry)
    {
        if (isset($entry['fieldset'])) {
            $entry['blueprint'] = $entry['fieldset'];
        }

        unset($entry['fieldset']);

        return $entry;
    }

    /**
     * Dump entry to markdown.
     *
     * @param array $entry
     * @return string
     */
    protected function dumpEntryToMarkdown($entry)
    {
        return isset($entry['content'])
            ? YAML::dumpFrontMatter(collect($entry)->except('content')->all()) . $entry['content']
            : YAML::dump($entry);
    }
}
