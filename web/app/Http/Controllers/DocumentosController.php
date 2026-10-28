<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Base documental: PDFs do fornecedor e dos planos/roteiros, listados por grupo e servidos por índice (sem expor caminhos). */
class DocumentosController extends Controller
{
    private const GRUPOS = [
        'fornecedor' => ['Documentos do fornecedor (Siemens)', 'drive/01/*/*.pdf'],
        'plano' => ['Plano e roteiros de manutenção preventiva', 'plano/*/*.pdf'],
    ];

    private function arquivos(string $grupo): array
    {
        $arquivos = glob(rtrim(config('cbm.docs_path'), '/\\').DIRECTORY_SEPARATOR.self::GRUPOS[$grupo][1]) ?: [];
        sort($arquivos);

        return $arquivos;
    }

    public function index(): View
    {
        $grupos = [];
        foreach (self::GRUPOS as $chave => [$titulo]) {
            $grupos[] = [
                'titulo' => $titulo,
                'arquivos' => collect($this->arquivos($chave))->map(fn ($f, $i) => [
                    'nome' => pathinfo($f, PATHINFO_FILENAME), 'mb' => filesize($f) / 1e6,
                    'rota' => route('documentos.download', [$chave, $i]),
                ]),
            ];
        }

        return view('documentos.index', ['grupos' => $grupos]);
    }

    public function download(string $grupo, int $indice): BinaryFileResponse
    {
        abort_unless(isset(self::GRUPOS[$grupo]), 404);
        $arquivo = $this->arquivos($grupo)[$indice] ?? abort(404);

        return response()->file($arquivo, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.rawurlencode(File::basename($arquivo)).'"',
        ]);
    }
}
