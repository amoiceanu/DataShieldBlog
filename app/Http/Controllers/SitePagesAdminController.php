<?php

namespace App\Http\Controllers;

use App\Services\SitePages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SitePagesAdminController extends Controller
{
    public function index(SitePages $pages)
    {
        return view('admin.site-pages', ['pages' => $pages->all()]);
    }

    public function update(Request $request)
    {
        $rules = ['pages' => 'required|array:'.implode(',', array_keys(config('site_pages')))];
        foreach (config('site_pages') as $key => $definition) {
            $rules['pages.'.$key] = 'required|array:enabled,menu';
            $rules['pages.'.$key.'.enabled'] = 'required|boolean';
            if (isset($definition['route'])) {
                $rules['pages.'.$key.'.menu'] = 'required|boolean';
            }
        }
        $data = $request->validate($rules)['pages'];
        DB::transaction(function () use ($data) {
            // Lock and preserve provider limits/retention, sharing the existing source of truth.
            DB::table('exposure_settings')->insertOrIgnore(['id' => 1, 'options' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('exposure_settings')->where('id', 1)->lockForUpdate()->first();
            $options = json_decode($row->options, true) ?? [];
            $options['enabled'] = (bool) $data['exposure']['enabled'];
            $options['menu'] = (bool) $data['exposure']['menu'];
            foreach (['breaches', 'mentions'] as $key) {
                $options[$key] = (bool) $data[$key]['enabled'];
            }
            DB::table('exposure_settings')->where('id', 1)->update(['options' => json_encode($options), 'updated_at' => now()]);
            foreach ($data as $key => $values) {
                if (in_array($key, ['exposure', 'breaches', 'mentions'])) {
                    continue;
                }
                DB::table('site_page_settings')->updateOrInsert(['key' => $key], ['enabled' => (bool) $values['enabled'], 'menu' => (bool) ($values['menu'] ?? false), 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return redirect()->route('admin.pages')->with('exposure_notice', 'Disponibilitatea paginilor și modulelor a fost actualizată.');
    }
}
