<?php
namespace App\UseCases;

use Exception;


abstract class CsvChunkReader
{
    protected int $chunkSize = 1000;


    private function trataTexto($txt)
{
    // Limita a 255 caracteres
    $linha = substr($txt, 0, 255);

    // Remove acentos e converte para ASCII
  try{
    $linha = mb_convert_encoding($linha, 'UTF-8', 'UTF-8');
      $msg = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $linha);
  }catch(Exception $e){

      $msg = $linha;
  }

    // Remove caracteres especiais indesejados (adicione conforme sua necessidade)
    $msg = str_replace([
        '!', '@', '#', '&', '$', '%', '*', '+', '-', '<', '>', '/', ';', ',', '\\', '.', '_', '(', ')', '°', '"', '\'', '?', '[', ']', '{', '}', '=', '^', '`', '|'
    ], '', $msg);

    // Remove espaços duplicados e trim final
    $msg = preg_replace('/\s+/', ' ', $msg);
    $msg = trim($msg);

    return $msg;
}

    /**
     * Lê um arquivo CSV em chunks, processando via yield.
     *
     * @param string $file Caminho do arquivo CSV
     * @return \Generator
     */
    public function readCsv(string $file,$colunas): \Generator
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new \Exception('Erro ao abrir o arquivo');
        }

        while (!feof($handle)) {
            $chunk = [];
            for ($i = 0; $i < $this->chunkSize && !feof($handle); $i++) {
                $row = fgetcsv($handle, separator:';');

                if ($row === false) {
                    continue;
                }
                $chunk[] = $this->processRow($row,$colunas); // permite customização
            }

            if (!empty($chunk)) {
                yield $chunk;
            }
        }

        fclose($handle);
        unlink($file); // Se quiser deletar depois de processar
    }

    /**
     * Permite sobrescrever para processar cada linha conforme necessidade
     * Por padrão, retorna a linha crua.
     */
  protected function processRow(array $row, $colunas)
{
    foreach ($row as &$line) {
        $line = $this->trataTexto($line);
    }

    if (count($colunas) !== count($row)) {
        throw new \InvalidArgumentException(
            'Número de colunas e valores não coincide. ' .
            'Esperado: ' . count($colunas) . ', recebido: ' . count($row) .
            '. Dados recebidos: [' . implode(', ', $row) . ']'
        );

    }

    return array_combine($colunas, $row);
}

}
