<?php
namespace App\UseCases;

use DB;
use Illuminate\Support\Facades\Http;

class ReadDirectoryUseCase
{

    public function __invoke($url)
    {
         // Busca todas as pastas já processadas
    $pastasProcessadas = DB::table('processados')->where('completo',1)->pluck('pasta');

    // Busca HTML remoto e extrai os links de diretórios válidos
    $html = Http::get($url)->body();
    preg_match_all('/href="([^"]+)"/i', $html, $matches);

    $pastasRemotas = collect($matches[1])->filter(function ($href) {
        return str_ends_with($href, '/')
            && !in_array($href, ['./', '../', 'temp/', '/dados/cnpj/']);
    });

    // Remove pastas já processadas
    $novasPastas = $pastasRemotas->diff($pastasProcessadas)->first();
    if(empty($novasPastas)){
        return null;
    }
    $pastaCriada = DB::table('processados')->where('pasta',$novasPastas)->first();
    if($pastaCriada){
        return $pastaCriada;
    }
    DB::table('processados')->insert(['pasta' => $novasPastas]);
    return DB::table('processados')->where('pasta',$novasPastas)->first();
    // Retorno pode ser um array, JSON, ou o que preferir
    }

}
