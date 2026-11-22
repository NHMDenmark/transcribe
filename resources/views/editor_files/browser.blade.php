<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Choose a file</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; margin: 0; padding: 16px; background: #f7f7f7; }
    h1 { font-size: 16px; margin: 0 0 8px; }
    .breadcrumb { font-size: 13px; color: #666; margin: 0 0 12px; }
    .breadcrumb a { color: #054791; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .upload-box { background: #fff; border: 1px dashed #bbb; border-radius: 6px; padding: 12px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
    .upload-box input[type=file] { flex: 1; }
    .upload-box button, .newfolder-box button { padding: 6px 14px; cursor: pointer; }
    #status { font-size: 13px; color: #666; margin-left: 8px; }
    .newfolder-box button { margin-bottom: 16px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
    .thumb, .folder { position: relative; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; cursor: pointer; color: #333; text-decoration: none; display: block; }
    .thumb:hover, .folder:hover { border-color: #888; }
    .thumb img { display: block; width: 100%; height: 90px; object-fit: cover; }
    .thumb .doc { height: 90px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: #054791; background: #eef3fa; text-transform: uppercase; }
    .thumb .name, .folder .name { font-size: 11px; padding: 4px 6px; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .folder { padding: 10px 6px; text-align: center; }
    .folder .icon { font-size: 28px; line-height: 1; }
    .del { position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,.55); color: #fff; border: 0; border-radius: 50%; width: 20px; height: 20px; line-height: 20px; text-align: center; cursor: pointer; font-size: 12px; padding: 0; }
    .empty { color: #888; font-size: 13px; }
</style>
</head>
<body>

<h1>{{ $type === 'images' ? 'Choose or upload an image' : 'Choose or upload a file' }}</h1>

<p class="breadcrumb">
    <a href="?type={{ $type }}&path=&CKEditorFuncNum={{ $funcNum }}">Uploads</a>
    @php($crumbTrail = '')
    @foreach(array_filter(explode('/', $path)) as $segment)
        @php($crumbTrail = $crumbTrail === '' ? $segment : $crumbTrail . '/' . $segment)
        / <a href="?type={{ $type }}&path={{ urlencode($crumbTrail) }}&CKEditorFuncNum={{ $funcNum }}">{{ $segment }}</a>
    @endforeach
</p>

<div class="upload-box">
    <input type="file" id="uploadInput" accept="{{ $accept }}">
    <button type="button" id="uploadBtn">Upload</button>
    <span id="status"></span>
</div>

<div class="newfolder-box">
    <button type="button" id="newFolderBtn">+ New folder</button>
</div>

<div class="grid" id="grid">
    @if($parentPath !== null)
        <a class="folder" href="?type={{ $type }}&path={{ urlencode($parentPath) }}&CKEditorFuncNum={{ $funcNum }}">
            <div class="icon">&#8617;</div>
            <div class="name">.. (up)</div>
        </a>
    @endif

    @foreach($folders as $folder)
        <a class="folder" href="?type={{ $type }}&path={{ urlencode($folder['path']) }}&CKEditorFuncNum={{ $funcNum }}" data-folder-path="{{ $folder['path'] }}" data-folder-name="{{ $folder['name'] }}">
            <button type="button" class="del" title="Delete folder">&times;</button>
            <div class="icon">&#128193;</div>
            <div class="name">{{ $folder['name'] }}</div>
        </a>
    @endforeach

    @foreach($files as $file)
        <div class="thumb" data-url="{{ $file['url'] }}" data-name="{{ $file['name'] }}">
            <button type="button" class="del" title="Delete">&times;</button>
            @if($file['image'])
                <img src="{{ $file['url'] }}" alt="">
            @else
                <div class="doc">{{ pathinfo($file['name'], PATHINFO_EXTENSION) }}</div>
            @endif
            <div class="name">{{ $file['name'] }}</div>
        </div>
    @endforeach

    @if($files->isEmpty() && $folders->isEmpty())
        <p class="empty">Nothing here yet. Upload the first one above.</p>
    @endif
</div>

<script>
const funcNum = {{ $funcNum }};
const currentPath = @json($path);
const browserType = @json($type);
const urls = @json($urls);
const csrfToken = document.querySelector('meta[name=csrf-token]').content;
const grid = document.getElementById('grid');

function post(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: Object.assign({'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'}, body instanceof FormData ? {} : {'Content-Type': 'application/json'}),
        body: body instanceof FormData ? body : JSON.stringify(body),
    }).then(function (r) { return r.json().then(function (data) { return {ok: r.ok, data: data}; }); });
}

function chooseFile(url) {
    window.opener.CKEDITOR.tools.callFunction(funcNum, url);
    window.close();
}

grid.addEventListener('click', function (e) {
    const folderDel = e.target.closest('.folder .del');
    if (folderDel) {
        // the folder itself is a link: do not also follow it
        e.preventDefault();
        e.stopPropagation();
        const folderEl = e.target.closest('.folder');
        if (!confirm('Delete the folder "' + folderEl.dataset.folderName + '" and everything in it? Texts that use these files will lose them.')) return;
        post(urls.deleteFolder, {path: folderEl.dataset.folderPath}).then(function (r) {
            if (r.ok) folderEl.remove(); else alert(r.data.message || 'Could not delete the folder.');
        });
        return;
    }

    const fileDel = e.target.closest('.thumb .del');
    if (fileDel) {
        e.stopPropagation();
        const thumb = e.target.closest('.thumb');
        if (!confirm('Delete "' + thumb.dataset.name + '"? Texts that use it will lose it.')) return;
        post(urls.delete, {name: thumb.dataset.name, path: currentPath}).then(function () { thumb.remove(); });
        return;
    }

    const thumb = e.target.closest('.thumb');
    if (thumb) chooseFile(thumb.dataset.url);
});

document.getElementById('newFolderBtn').addEventListener('click', function () {
    const name = prompt('Folder name:');
    if (!name) return;

    post(urls.createFolder, {name: name, path: currentPath}).then(function (r) {
        if (!r.ok) { alert((r.data.message) || 'Could not create the folder.'); return; }
        window.location.href = '?type=' + browserType + '&path=' + encodeURIComponent(currentPath) + '&CKEditorFuncNum=' + funcNum;
    });
});

document.getElementById('uploadBtn').addEventListener('click', function () {
    const input = document.getElementById('uploadInput');
    const status = document.getElementById('status');
    if (!input.files.length) return;

    const formData = new FormData();
    formData.append('upload', input.files[0]);
    formData.append('path', currentPath);
    formData.append('type', browserType);

    status.textContent = 'Uploading…';
    post(urls.upload, formData).then(function (r) {
        if (r.ok && r.data.url) { chooseFile(r.data.url); return; }
        const errors = r.data && r.data.errors ? Object.values(r.data.errors).flat().join(' ') : '';
        status.textContent = errors || (r.data && r.data.message) || 'The upload failed.';
    }).catch(function () { status.textContent = 'The upload failed.'; });
});
</script>
</body>
</html>
