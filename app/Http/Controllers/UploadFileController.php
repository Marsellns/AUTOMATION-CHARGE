<?php

namespace App\Http\Controllers;

use App\Models\UploadFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UploadFileController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.upload-file.index');
    }

    public function data(): JsonResponse
    {
        return DataTables::of(UploadFile::query()->latest('id'))
            ->addIndexColumn()
            ->addColumn('aksi', fn (UploadFile $f) => view('infrastruktur.upload-file.partials.aksi', ['file' => $f])->render())
            ->addColumn('file_link', function (UploadFile $f) {
                if (empty($f->file_path)) return '-';
                $name = basename($f->file_path);
                $url  = asset('storage/' . $f->file_path);
                return '<a href="' . e($url) . '" target="_blank" class="text-decoration-none">'
                     . '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zM9.5 3A1.5 1.5 0 0 1 8 1.5V0H4a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5H9.5z"/></svg>'
                     . e($name) . '</a>';
            })
            ->editColumn('update_time', fn (UploadFile $f) => $f->update_time?->format('Y-m-d H:i') ?? '-')
            ->rawColumns(['aksi', 'file_link'])
            ->toJson();
    }

    public function create(): View
    {
        return view('infrastruktur.upload-file.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:1000',
            'file'       => 'required|file|mimes:pdf|max:10240',
        ]);

        $path = $request->file('file')->store('upload-files', 'public');

        UploadFile::create([
            'keterangan'  => $request->keterangan,
            'file_path'   => $path,
            'update_by'   => auth()->user()->name,
            'update_time' => now(),
        ]);

        return redirect()->route('infrastruktur.upload-file.index')
            ->with('success', 'File berhasil diupload.');
    }

    public function edit(UploadFile $uploadFile): View
    {
        return view('infrastruktur.upload-file.edit', ['file' => $uploadFile]);
    }

    public function update(Request $request, UploadFile $uploadFile)
    {
        $request->validate([
            'keterangan' => 'required|string|max:1000',
            'file'       => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = [
            'keterangan'  => $request->keterangan,
            'update_by'   => auth()->user()->name,
            'update_time' => now(),
        ];

        if ($request->hasFile('file')) {
            // Hapus file lama
            if ($uploadFile->file_path) {
                Storage::disk('public')->delete($uploadFile->file_path);
            }
            $data['file_path'] = $request->file('file')->store('upload-files', 'public');
        }

        $uploadFile->update($data);

        return redirect()->route('infrastruktur.upload-file.index')
            ->with('success', 'File berhasil diperbarui.');
    }

    public function destroy(UploadFile $uploadFile)
    {
        if ($uploadFile->file_path) {
            Storage::disk('public')->delete($uploadFile->file_path);
        }
        $uploadFile->delete();

        return redirect()->route('infrastruktur.upload-file.index')
            ->with('success', 'File berhasil dihapus.');
    }
}
