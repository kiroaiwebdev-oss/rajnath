<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Showcase\Gallery;
use App\Domain\Showcase\Profile;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use App\Support\Uploads\SafeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin › About & awards: biography, stats, home-page text, portraits, and the About/Awards lists. */
class ProfileController extends Controller
{
    public static function field(string $key, ?string $locale = null): string
    {
        return str_replace('.', '__', $key).($locale ? '__'.$locale : '');
    }

    /** Text fields that also take a Hindi version (stats are numbers). */
    public static function translatable(string $key): bool
    {
        return ! str_starts_with($key, 'profile.stat_');
    }

    public function edit(SettingsRepository $settings): View
    {
        $this->authorize('content.author');

        return view('admin.profile.edit', [
            'values' => $settings->all(), 'profile' => app(Profile::class),
            'entries' => DB::table('profile_entries')->orderBy('sort')->orderBy('id')->get()->groupBy('section'),
        ]);
    }

    public function saveText(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $this->authorize('content.publish');
        $rules = [];
        foreach (Profile::TEXT as $key => [, , $rows]) {
            $max = $rows > 4 ? 6000 : ($rows > 1 ? 1200 : 200);
            $rules[self::field($key)] = ['nullable', 'string', 'max:'.$max];
            if (self::translatable($key)) {
                $rules[self::field($key, 'hi')] = ['nullable', 'string', 'max:'.$max];
            }
        }
        foreach (array_keys(Profile::PHOTOS) as $key) {
            $rules[self::field($key)] = ['nullable', 'file', 'max:8192'];
        }
        $d = $request->validate($rules);

        foreach (Profile::TEXT as $key => [, $default]) {
            $value = trim((string) ($d[self::field($key)] ?? ''));
            // storing the default as empty keeps its translations working
            $value = $value === '' || $value === $default ? null : $value;
            if ($settings->get($key) !== $value) {
                $settings->set($key, $value, false, 'profile');
            }
            if (self::translatable($key)) {
                $hi = trim((string) ($d[self::field($key, 'hi')] ?? '')) ?: null;
                if ($settings->get($key.'.hi') !== $hi) {
                    $settings->set($key.'.hi', $hi, false, 'profile');
                }
            }
        }
        foreach (array_keys(Profile::PHOTOS) as $key) {
            if ($request->hasFile(self::field($key))) {
                $img = SafeImage::storeForWeb($request->file(self::field($key)), Gallery::DISK, 'profile', self::field($key));
                $old = $settings->get($key);
                $settings->set($key, 'uploads/'.$img['path'], false, 'profile');
                Gallery::deleteFile($old);
            }
        }
        Profile::forget();

        return redirect()->route('admin.profile.edit')->with('status', 'Profile saved. The website and the app show it now.');
    }

    public function saveEntry(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('content.publish');
        $d = $request->validate([
            'id' => ['nullable', 'integer', 'exists:profile_entries,id'],
            'section' => ['required', Rule::in(array_keys(Profile::SECTIONS))],
            'title' => ['required', 'string', 'max:300'],
            'title_hi' => ['nullable', 'string', 'max:300'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'detail_hi' => ['nullable', 'string', 'max:2000'],
            'year' => ['nullable', 'string', 'max:40'],
            'group' => ['nullable', Rule::in(array_keys(Profile::AWARD_GROUPS))],
            'link_url' => ['nullable', 'url:https', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'max:8192'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
        $old = ($d['id'] ?? null) ? DB::table('profile_entries')->find($d['id']) : null;
        $row = ['section' => $d['section'], 'title' => $d['title'], 'title_hi' => $d['title_hi'] ?? null, 'detail' => $d['detail'] ?? null,
            'detail_hi' => $d['detail_hi'] ?? null, 'year' => $d['year'] ?? null, 'group' => $d['section'] === 'award' ? ($d['group'] ?? null) : null,
            'link_url' => $d['link_url'] ?? null, 'featured' => (bool) ($d['featured'] ?? false), 'published' => (bool) ($d['published'] ?? false),
            'sort' => (int) ($d['sort'] ?? ($old->sort ?? ((int) DB::table('profile_entries')->where('section', $d['section'])->max('sort') + 10))), 'updated_at' => now()];
        if ($request->hasFile('image')) {
            $row['image_path'] = 'uploads/'.SafeImage::storeForWeb($request->file('image'), Gallery::DISK, 'profile', 'image')['path'];
        } elseif ($d['remove_image'] ?? false) {
            $row['image_path'] = null;
        }
        if ($old) {
            DB::table('profile_entries')->where('id', $old->id)->update($row);
            if (array_key_exists('image_path', $row) && $old->image_path !== $row['image_path']) {
                Gallery::deleteFile($old->image_path);
            }
            $id = $old->id;
        } else {
            $id = DB::table('profile_entries')->insertGetId($row + ['created_at' => now()]);
        }
        Profile::forget();
        $audit->record('content.profile_entry_saved', null, ['entry' => $id, 'section' => $row['section'], 'title' => $row['title'], 'published' => $row['published']]);

        return redirect()->to(route('admin.profile.edit').'#'.$row['section'])->with('status', Profile::SECTIONS[$row['section']].': saved.');
    }

    public function destroyEntry(int $id, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('content.publish');
        $e = DB::table('profile_entries')->find($id);
        abort_unless($e, 404);
        DB::table('profile_entries')->where('id', $id)->delete();
        Gallery::deleteFile($e->image_path);
        Profile::forget();
        $audit->record('content.profile_entry_deleted', null, ['entry' => $id, 'section' => $e->section, 'title' => $e->title]);

        return redirect()->to(route('admin.profile.edit').'#'.$e->section)->with('status', 'Deleted.');
    }
}
