<?php
namespace App\UseCases;

use DB;

class SaveDirectoryUseCase
{

    public function __invoke($directory)
    {

     return  DB::table('processados')->where('id' , $directory->id)->update(['completo' => 1]);

    }

}
