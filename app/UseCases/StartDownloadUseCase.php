<?php
namespace App\UseCases;

use App\Exceptions\ArquivoImportadoException;
use App\Utils\FileCleaner;
use DB;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Nette\Utils\FileSystem;
use ZipArchive;

class StartDownloadUseCase
{

 private function fileCorrupted($file)
{
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        return true; // Não conseguiu abrir => corrompido
    }

    if ($zip->numFiles === 0) {
        $zip->close();
        return true; // Nenhum arquivo => suspeita de corrupção
    }

    // Tenta ler cada arquivo do ZIP para validar
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $stream = $zip->getStream($stat['name']);
        if (!$stream) {
            $zip->close();
            return true; // Falha ao abrir o conteúdo => corrompido
        }
        fclose($stream);
    }

    $zip->close();
    return false; // Se chegou aqui, o ZIP está íntegro
}


    private function download($file)
    {

        FileCleaner::cleanTemporaryFiles();
        $name = basename($file);

        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;

        if(file_exists($zipPath)) {
            echo "Arquivo já baixado: $zipPath\n";
            // Verifica se o arquivo está corrompido
            // Se estiver corrompido, remove o arquivo
            // e baixa novamente
            // Se não estiver corrompido, retorna o caminho do arquivo
            // e não baixa novamente
            echo "Verificando integridade do arquivo...\n";
            echo "Arquivo: $zipPath\n";
            echo "Tamanho do arquivo: " . filesize($zipPath) . " bytes\n";
            if ($this->fileCorrupted($zipPath)) {
                echo "Arquivo corrompido, removendo e baixando novamente...\n";
                unlink($zipPath);
            } else {
                echo "Arquivo não corrompido, retornando...\n";
                return $this->returnFile($zipPath);
            }
        }
        $client = new Client();
        try {

            dd($file);
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

        $zip = new ZipArchive;
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
