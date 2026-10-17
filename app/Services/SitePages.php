<?php

namespace App\Services;

use App\Services\Exposure\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SitePages
{
    public function all(): array
    {
        $rows = Schema::hasTable('site_page_settings') ? DB::table('site_page_settings')->get()->keyBy('key') : collect();
        $exposure = app(Settings::class)->all();
        $pages = [];
        foreach (config('site_pages') as $key => $definition) {
            $row = $rows->get($key);
            $enabled = match ($key) {
                'exposure' => $exposure['enabled'],'breaches','mentions' => $exposure[$key],default => $row ? (bool) $row->enabled : true
            };
            $menu = $key === 'exposure' ? $exposure['menu'] : ($row ? (bool) $row->menu : true);
            $pages[$key] = $definition + ['enabled' => (bool) $enabled, 'menu' => (bool) $menu];
        }
        foreach ($pages as $key => $page) {
            $pages[$key]['available'] = $page['enabled'] && (! isset($page['parent']) || $pages[$page['parent']]['enabled']);
        }

        return $pages;
    }

    public function enabled(string $key): bool
    {
        return $this->all()[$key]['available'] ?? false;
    }

    public function visible(string $key): bool
    {
        $p = $this->all()[$key] ?? [];

        return ($p['available'] ?? false) && ($p['menu'] ?? false);
    }

    public function navigation(array $keys): array
    {
        $pages = $this->all();
        $links = [];
        foreach ($keys as $key) {
            if (isset($pages[$key]) && $pages[$key]['available'] && $pages[$key]['menu'] && isset($pages[$key]['route'])) {
                $links[$key] = $pages[$key];
            }
        }

        return $links;
    }
}
