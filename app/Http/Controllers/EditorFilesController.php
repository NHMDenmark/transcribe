<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The "browse the server" popup of the text editor (CKEditor's own file browser plugin): pick, upload
 * and delete the images and files that texts link to, in folders under public/uploads. For root and
 * admin (capability manage-content, set on the routes).
 *
 * Everything goes through the "local" disk, whose root is public/. A path that comes from the
 * request is never trusted: it is rebuilt from its plain segments (safeSubPath), so it can not leave
 * public/uploads whatever is written in it. Only a short list of types can be uploaded, checked on
 * the content of the file and not on its name, and never anything a server could run or a browser
 * could run scripts from (no PHP, no SVG, no HTML).
 */
class EditorFilesController extends Controller
{
    private const ROOT = 'uploads';

    private const IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const DOCUMENTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

    private const MAX_KILOBYTES = 10240;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Segments "", "." and ".." (and hidden ones) are dropped: nothing left can mean "go up". */
    private function safeSubPath(?string $path): string
    {
        return collect(explode('/', str_replace('\\', '/', (string) $path)))
            ->filter(fn($segment) => $segment !== '' && !Str::startsWith($segment, '.'))
            ->implode('/');
    }

    private function fullPath(string $subPath): string
    {
        return $subPath === '' ? self::ROOT : self::ROOT . '/' . $subPath;
    }

    /** The address a text stores: from the site root, so it survives a change of domain. */
    private function publicPath(string $fullPath, string $name): string
    {
        return rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/') . '/' . $fullPath . '/' . rawurlencode($name);
    }

    private function disk()
    {
        return Storage::disk('local');
    }

    public function browse(Request $request)
    {
        $imagesOnly = $request->query('type') === 'images';
        $allowed = $imagesOnly ? self::IMAGES : array_merge(self::IMAGES, self::DOCUMENTS);

        $subPath = $this->safeSubPath($request->query('path'));
        $fullPath = $this->fullPath($subPath);

        $folders = collect($this->disk()->exists($fullPath) ? $this->disk()->directories($fullPath) : [])
            ->reject(fn($path) => Str::startsWith(basename($path), '.'))
            ->map(fn($path) => ['name' => basename($path), 'path' => ltrim($subPath . '/' . basename($path), '/')])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $files = collect($this->disk()->exists($fullPath) ? $this->disk()->files($fullPath) : [])
            ->reject(fn($path) => Str::startsWith(basename($path), '.'))
            ->filter(fn($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $allowed, true))
            ->sortByDesc(fn($path) => $this->disk()->lastModified($path))
            ->map(fn($path) => [
                'name' => basename($path),
                'url' => $this->publicPath($fullPath, basename($path)),
                'image' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGES, true),
            ])
            ->values();

        $parentPath = null;
        if ($subPath !== '') {
            $segments = explode('/', $subPath);
            array_pop($segments);
            $parentPath = implode('/', $segments);
        }

        return view('editor_files.browser', [
            'folders' => $folders,
            'files' => $files,
            'path' => $subPath,
            'parentPath' => $parentPath,
            'funcNum' => (int) $request->query('CKEditorFuncNum'),
            'type' => $imagesOnly ? 'images' : 'files',
            'accept' => implode(',', array_map(fn($e) => '.' . $e, $allowed)),
            'urls' => [
                'upload' => url('editor-files/upload'),
                'delete' => url('editor-files/delete'),
                'createFolder' => url('editor-files/create-folder'),
                'deleteFolder' => url('editor-files/delete-folder'),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $imagesOnly = $request->input('type') === 'images';
        $allowed = $imagesOnly ? self::IMAGES : array_merge(self::IMAGES, self::DOCUMENTS);

        $request->validate(['upload' => ['required', 'file', 'mimes:' . implode(',', $allowed), 'max:' . self::MAX_KILOBYTES]]);

        $fullPath = $this->fullPath($this->safeSubPath($request->input('path')));
        $file = $request->file('upload');

        // the extension comes from what the file really is, not from what it was called
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $extension = strtolower($extension === 'jpeg' ? 'jpg' : $extension);
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $name = $base . '_' . time() . '.' . $extension;

        $this->disk()->putFileAs($fullPath, $file, $name);

        return response()->json(['name' => $name, 'url' => $this->publicPath($fullPath, $name)]);
    }

    public function delete(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        $fullPath = $this->fullPath($this->safeSubPath($request->input('path')));
        $name = basename($request->input('name'));   // no "../" out of the current folder

        if (!Str::startsWith($name, '.')) {
            $this->disk()->delete($fullPath . '/' . $name);
        }

        return response()->json(['ok' => true]);
    }

    public function createFolder(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $name = Str::slug(basename(trim($request->input('name'))));
        if ($name === '') {
            return response()->json(['message' => 'Invalid folder name.'], 422);
        }

        $subPath = $this->safeSubPath($request->input('path'));
        $fullPath = $this->fullPath($subPath) . '/' . $name;

        if ($this->disk()->exists($fullPath)) {
            return response()->json(['message' => 'A folder with that name already exists.'], 422);
        }

        $this->disk()->makeDirectory($fullPath);

        return response()->json(['name' => $name, 'path' => ltrim($subPath . '/' . $name, '/')]);
    }

    public function deleteFolder(Request $request)
    {
        $request->validate(['path' => 'nullable|string']);

        $subPath = $this->safeSubPath($request->input('path'));
        if ($subPath === '') {
            return response()->json(['message' => 'The main folder cannot be deleted.'], 422);
        }

        $this->disk()->deleteDirectory($this->fullPath($subPath));

        return response()->json(['ok' => true]);
    }
}
