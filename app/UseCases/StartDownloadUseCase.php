<?php
namespace App\UseCases;

use App\Exceptions\ArquivoImportadoException;
use DB;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class StartDownloadUseCase
{

    private function download($file)
    {

        $zipPath = tempnam(sys_get_temp_dir(), 'zip_');

        $client = new Client();
        try {
            $client->request('GET', $file, [
                'sink'     => $zipPath,
                'progress' => function (
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) {
                    if ($downloadTotal > 0) {
                        $percent = round($downloadedBytes / $downloadTotal * 100, 2);
                        echo "\rBaixado: $percent% ($downloadedBytes de $downloadTotal bytes)";
                    } else {
                        echo "\rBaixado: $downloadedBytes bytes (tamanho total desconhecido)";
                    }
                    flush();
                },
            ]);
        } catch (RequestException $e) {
            throw new Exception('Erro ao baixar ZIP');
        }
        $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('unzip_', true);
        mkdir($extractDir);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractDir);
            $zip->close();
        } else {
            unlink($zipPath);
            throw new Exception('Não foi possível descompactar o ZIP');
        }
        unlink($zipPath);
        return $this->returnFile($extractDir);
    }

    private function returnFile($extractDir)
    {
        $arquivos = glob($extractDir . DIRECTORY_SEPARATOR . '*');

        // Filtra só arquivos (ignora subdiretórios, se existirem)
        $arquivos = array_filter($arquivos, 'is_file');
        if (! empty($arquivos)) {
            $primeiroArquivo = $arquivos[0];
            return $primeiroArquivo;
        } else {
            throw new Exception('Nenhum arquivo encontrado no diretório extraído!');
        }
    }
    public function __invoke($file, $folder, $url)
    {

        $incompleto = DB::table('completados')
            ->where('processado_id', $folder->id)
            ->where('arquivo', $file)

            ->first();


        if ($incompleto == null || empty($incompleto->concluido_em)) {
            if ($incompleto == null) {
                DB::table('completados')->insert([
                    'processado_id' => $folder->id,
                    'arquivo'       => $file,
                    'iniciado_em'   => now(),
                ]);
            }
            return $this->download($url . $folder->pasta . $file);
        } else {
            throw new ArquivoImportadoException("Arquivo já concluído");

        }
    }

}
