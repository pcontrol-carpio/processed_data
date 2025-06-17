<?php
namespace App\Http\Controllers;

use App\UseCases\CompleteDownloadUseCase;
use App\UseCases\ListDirectoryUseCase;
use App\UseCases\ReadDirectoryUseCase;
use App\UseCases\SaveDirectoryUseCase;
use App\UseCases\StartDownloadUseCase;
use App\UseCases\UseCaseFactory;

class DirectoryController extends Controller
{

    public function __construct(
        private ReadDirectoryUseCase $readDirectoryUseCase,
        private SaveDirectoryUseCase $saveDirectoryUseCase,
        private ListDirectoryUseCase $listDirectoryUseCase,
        private StartDownloadUseCase $startDownloadUseCase,
        private CompleteDownloadUseCase $completeDownloadUseCase,
        private UseCaseFactory $useCaseFactory
    ) {}
    public function findDirectory($url)
    {
        $readDir = $this->readDirectoryUseCase;
        return $readDir($url);

    }

    public function finishDirectory($directory)
    {

        $saveDir = $this->saveDirectoryUseCase;
        return $saveDir($directory);
    }

    public function downloadFile($file, $folder, $url)
    {

        $downloadFile = $this->startDownloadUseCase;
        return $downloadFile($file, $folder, $url);
    }

    public function processFile($file, $type,$fileName,$directory)
    {
        $factory = $this->useCaseFactory;
        $completed =  $factory($file, $type);
        if($completed){
            $completeUseCase = $this->completeDownloadUseCase;
            $completeUseCase($directory,$fileName);
        }
        return $completed;

    }

    public function listDirectory($url)
    {
        $listDir = $this->listDirectoryUseCase;
        return $listDir($url);

    }
}
