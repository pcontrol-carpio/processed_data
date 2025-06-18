<?php
namespace App\Console\Commands;

use App\Exceptions\ArquivoImportadoException;
use App\Http\Controllers\DirectoryController;
use Exception;
use Illuminate\Console\Command;

class ReadDirecotryCommand extends Command
{
    public function __construct(private DirectoryController $directoryController)
    {
        parent::__construct();
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:read-directory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // Limpa arquivos .txt e diretórios que começam com unzip na pasta /tmp
        $files = glob('/tmp/*.txt');
        $filesCleaned = glob('/tmp/cleaned*');
        $dirs  = glob('/tmp/unzip*', GLOB_ONLYDIR);

        foreach ($files as $file) {
            @unlink($file);
        }
        foreach ($filesCleaned as $file) {
            @unlink($file);
        }

        foreach ($dirs as $dir) {
            exec("rm -rf " . escapeshellarg($dir));
        }
        $url = env('URL_BASE');

        $current_directory = $this->directoryController->findDirectory($url);
        if (empty($current_directory)) {
            $this->info("Nenhum diretorio novo para ler");
            return;
        }
        $folder = $current_directory->pasta;
        if (! empty($current_directory)) {
            $listDirectory = $this->directoryController->listDirectory($url . $folder);
            $processeds    = array();
            foreach ($listDirectory as $type => $files) {

                $myFiles = array_reverse(collect($files)->toArray());

                $this->info("Iniciando download dos arquivos da pasta {$type}");
                foreach ($myFiles as $file) {
                    $processeds[$file] = false;

                    $this->warn("Iniciando download do arquivo {$file}");
                    try {
                        $file_csv = $this->directoryController->downloadFile($file, $current_directory, $url);

                        $processed = $this->directoryController->processFile($file_csv, $type, $file, $current_directory);
                        @unlink($file_csv);
                        if ($processed) {
                            $processeds[$file] = true;

                            $this->info("Arquivo {$file_csv} processado com sucesso");
                        } else {
                            $this->error("Erro ao processar arquivo {$file_csv}");
                        }
                    } catch (ArquivoImportadoException $e) {
                        $this->error("Arquivo {$file} já foi processado anteriormente");
                        $processeds[$file] = true;
                    } catch (Exception $e) {
                        $this->error($e->getMessage());
                    }
                }

            }

            if (($failed = array_search(false, $processeds, true)) !== false) {
                echo "File '{$failed}' failed to process.";
            } else {
                $this->directoryController->finishDirectory($current_directory);
            }
            $this->info("Todos os arquivos foram processados");
            $this->info("Processamento finalizado");
            $this->info("Resultados do processamento:");
            foreach ($processeds as $file => $processed) {
                if ($processed) {
                    $this->info("Arquivo {$file} processado com sucesso");
                } else {
                    $this->error("Erro ao processar arquivo {$file}");
                }
            }
            $this->info("Resultados do processamento: ");
            $this->info("Processados: " . count($processeds));
            $this->info("Arquivos processados: " . count(array_filter($processeds)));

        }
    }
}
