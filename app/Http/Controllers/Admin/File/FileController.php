<?php

namespace App\Http\Controllers\Admin\File;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public static Filesystem $drive;

    public function __construct()
    {
        static::$drive = Storage::disk('s3');
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public static function getDrive(): Filesystem
    {
        if (!isset(self::$drive)) {
            static::$drive = Storage::disk('s3');
        }

        return static::$drive;
    }

    public function index(Request $request)
    {
        $path = $request->query('path');

        $dir = static::$drive->directories($path);

        $files = [];
        foreach (static::$drive->files($path) as $filepath) {
            $files[] = [
                'filepath' => $filepath,
                'size'     => static::$drive->size($filepath),
                'url'      => static::$drive->url($filepath),
            ];
        }

        return $this->response()->withData(compact('dir', 'files'))->respond();
    }

    public function store(Request $request): Response
    {
        $input = $request->validate([
            'file' => 'required|file|max:10240', // 设置最大文件大小为 10MB
        ]);

        $file = $input['file'];

        $upload_path = $request->input('upload_path');

        $filename = $file->getClientOriginalName();

        $filepath = static::$drive->putFileAs($upload_path, $file, $filename);

        //        $url = static::$drive->url($filepath);

        return $this->response()->withData([
            'filepath' => $filepath,
            //            'url'      => $url,
            //            'name'     => basename($filepath),
            //            'extname'  => pathinfo($filename, PATHINFO_EXTENSION),
        ])->respond();
    }

    public function show(string $id)
    {
        $filePath = 'uploads/'.$filename;

        if (Storage::disk('minio')->exists($filePath)) {
            $url = Storage::disk('minio')->temporaryUrl($filePath, now()->addMinutes(5));

            return response()->json(['url' => $url]);
        }

        return response()->json(['error' => 'File not found'], 404);
    }

    public function update(Request $request, string $id) {}

    public function destroy(string $id)
    {
        $filePath = 'uploads/'.$filename;

        if (Storage::disk('minio')->exists($filePath)) {
            Storage::disk('minio')->delete($filePath);

            $this->response()->withMessages(['File deleted successfully']);

            return response()->json();
        }

        return response()->json(['error' => 'File not found'], 404);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
