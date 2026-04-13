<?php
namespace App\UseCases;

use DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReadDirectoryUseCase
{

    public function __invoke($url)
    {
         // Busca todas as pastas já processadas
    $pastasProcessadas = DB::table('processados')->where('completo',1)->pluck('pasta');


    // Busca HTML remoto e extrai os links de diretórios válidos
    $html = Http::retry(20, 5)->get($url)->body();


    $novasPastas= date( 'Y-m') . '/';
    $pastaCriada = DB::table('processados')->where('pasta',$novasPastas)->first();
    if($pastaCriada){
        if($pastaCriada->completo == 1){
            Log::info("Pasta {$novasPastas} já processada");
            return null;
        }
        return $pastaCriada;
    }

    DB::table('processados')->insert(['pasta' => $novasPastas]);

    dd($novasPastas);
    return DB::table('processados')->where('pasta',$novasPastas)->first();
    // Retorno pode ser um array, JSON, ou o que preferir
    }

}
