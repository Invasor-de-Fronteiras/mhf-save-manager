<?php

use Doctrine\Common\Collections\Criteria;
use MHFSaveManager\Controller\BinaryController;
use MHFSaveManager\Controller\CharacterController;
use MHFSaveManager\Controller\DistributionsController;
use MHFSaveManager\Controller\SaveDataController;
use MHFSaveManager\Controller\RoadShopController;
use MHFSaveManager\Database\EM;
use MHFSaveManager\Model\Character;
use MHFSaveManager\Model\Distribution;
use MHFSaveManager\Model\NormalShopItem;
use MHFSaveManager\Service\CompressionService;
use MHFSaveManager\Service\ResponseService;
use Pecee\SimpleRouter\SimpleRouter;

SimpleRouter::get('/', function() {
    CharacterController::Index();
});

SimpleRouter::get('/language/{locale}', function($locale) {
    if (!is_dir(LOCALE_DIR)) {
        ResponseService::SendNotFound('Language not found: ' . $locale);
    }
    
    $_SESSION['locale'] = $locale;
    ResponseService::SendBackToOrigin();
});


SimpleRouter::get('/servertools/distributions', function() {
    DistributionsController::Index();
});

SimpleRouter::post('/servertools/distributions/save', function() {
    if (!isset($_POST['id']) ||
        !isset($_POST['type']) ||
        !isset($_POST['characterId']) ||
        !isset($_POST['timesacceptable']) ||
        !isset($_POST['name']) ||
        !isset($_POST['desc']) ||
        !isset($_POST['deadline']) ||
        !isset($_POST['minhr']) ||
        !isset($_POST['maxhr']) ||
        !isset($_POST['minsr']) ||
        !isset($_POST['maxsr']) ||
        !isset($_POST['mingr']) ||
        !isset($_POST['maxgr']) ||
        !isset($_POST['items'])) {
        ResponseService::SendUnprocessableEntity();
    }
    
    DistributionsController::EditDistribution();
});

SimpleRouter::get('/servertools/distributions/export', function() {
    DistributionsController::ExportDistributions();
});

SimpleRouter::post('/servertools/distributions/import', function() {
    if ($_FILES["distributionCSV"]["error"] != UPLOAD_ERR_OK) {
        ResponseService::SendServerError('Error while uploading, check storage permissions for the TMP folder!');
    }
    
    DistributionsController::ImportDistributions();
});

SimpleRouter::post('/servertools/distributions/delete/{id}', function($id) {
    /** @var Distribution $dist */
    $dist = EM::getInstance()->getRepository('MHFSaveManager\Model\Distribution')->find($id);
    if (!$dist) {
        ResponseService::SendNotFound();
    }
    $em = EM::getInstance();
    $em->remove($dist);
    $em->flush();
    
    ResponseService::SendOk();
});

SimpleRouter::get('/servertools/roadshop', function() {
    RoadShopController::Index();
});

SimpleRouter::post('/servertools/roadshop/save', function() {
    if (!isset($_POST['item']) ||
        !isset($_POST['category']) ||
        !isset($_POST['cost']) ||
        !isset($_POST['grank']) ||
        !isset($_POST['tradeQuantity']) ||
        !isset($_POST['maximumQuantity']) ||
        !isset($_POST['boughtQuantity']) ||
        !isset($_POST['roadFloors']) ||
        !isset($_POST['fatalis'])) {
        ResponseService::SendUnprocessableEntity();
    }
    
    RoadShopController::EditRoadShopItem();
});

SimpleRouter::get('/servertools/roadshop/export', function() {
    RoadShopController::ExportRoadShopItems();
});

SimpleRouter::post('/servertools/roadshop/import', function() {
    if ($_FILES["roadShopCSV"]["error"] != UPLOAD_ERR_OK) {
        ResponseService::SendServerError('Error while uploading, check storage permissions for the TMP folder!');
    }
    
    RoadShopController::ImportRoadShopItems();
});

SimpleRouter::post('/servertools/roadshop/delete/{id}', function($id) {
    /** @var NormalShopItem $item */
    $item = EM::getInstance()->getRepository('MHFSaveManager\Model\NormalShopItem')->find($id);
    if (!$item) {
        ResponseService::SendNotFound();
    }
    $em = EM::getInstance();
    $em->remove($item);
    $em->flush();
    
    ResponseService::SendOk();
});

SimpleRouter::get('/character/{id}/reset', function($id) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::Reset($character);
});

/*
 * ------------- EDIT ROUTES -------------
*/

SimpleRouter::get('/character/{id}/edit', function($id) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::Edit($character);
});

SimpleRouter::post('/character/{id}/edit/setname/', function($id) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character || !isset($_POST['name'])) {
        ResponseService::SendNotFound();
    }
    $name = $_POST['name'];
    CharacterController::WriteToSavedata($character, "SetName", $name);
    $character->setName($name);
    EM::getInstance()->flush();
    ResponseService::SendOk();
});

SimpleRouter::post('/character/{id}/edit/item/{box}/{slot}', function($id, $boxtype, $slot) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    if (!isset($_POST['item_id']) || !isset($_POST['item_quantity'])) {
        ResponseService::SendUnprocessableEntity('Could not process entity! Data missing!');
    }
    
    CharacterController::WriteToSavedata($character, "Set" . ucfirst($boxtype). "Slot", $slot);
    ResponseService::SendOk();
});

SimpleRouter::post('/character/{id}/edit/resetloginboost', function($id) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    CharacterController::ResetLoginboost($character);
});

SimpleRouter::post('/character/{id}/edit/{property}/{value}', function($id, $property, $value) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    $method = "Set" . ucfirst(substr($property, 3));
    
    if (method_exists(Character::class, $method)) {
        $character->$method($value);
        EM::getInstance()->flush();
        ResponseService::SendOk();
    } elseif (method_exists(SaveDataController::class, $method)) {
        CharacterController::WriteToSavedata($character, $method, $value);
        ResponseService::SendOk();
    } else {
        ResponseService::SendNotFound();
    }
    
});



/*SimpleRouter::get('/character/{id}/edit/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character && in_array($binary, BinaryController::getBinaryTypes())) {
        ResponseService::SendNotFound();
    }
    
    $action = 'Edit' . ucfirst(strtolower($binary));

    BinaryController::$action($character);
});*/

/*
 * ------------- BACKUP ROUTES -------------
*/

SimpleRouter::get('/character/{id}', function($id) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::Backup($character);
});

SimpleRouter::get('/character/{id}/decompress', function($id) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    file_put_contents(sprintf('%s\\tmp\\%s.bin', ROOT_DIR, $id), CompressionService::Decompress($character->getSavedata()));
    
    ResponseService::SendOk();
});

SimpleRouter::get('/character/{id}/compress', function($id) {
    /** @var Character $character */
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    file_put_contents(sprintf('%s\\tmp\\compressed_%s.bin', ROOT_DIR, $id), CompressionService::Compress(CompressionService::Decompress($character->getSavedata())));
    
    ResponseService::SendOk();
});

SimpleRouter::post('/character/{id}/backup/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    if ($binary === "all") {
        CharacterController::BackupAll($character);
        exit;
    }
    
    if (CharacterController::CreateBackup($character, $binary)) {
        ResponseService::SendOk();
    }
    ResponseService::SendServerError();
});

SimpleRouter::post('/character/{id}/backupdecomp/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    if (CharacterController::CreateBackup($character, $binary, true)) {
        ResponseService::SendOk();
    }
    ResponseService::SendServerError();
});

SimpleRouter::get('/character/{id}/backup/{binary}/{backup_file}', function($id, $binary, $backup_file) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character ||
        !file_exists($path = sprintf('%s/storage/%s/%s/%s',ROOT_DIR, $binary, $character->getId(), $backup_file)))
    {
        ResponseService::SendNotFound();
    }
    
    ResponseService::SendDownload($path);
}, ['defaultParameterRegex' => '[\w\-\.]+']);

SimpleRouter::post('/character/{id}/replace/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::ReplaceSavedata($character, $binary);
});

SimpleRouter::post('/character/{id}/compressentry/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::EntryCompression($character, $binary, $_POST['decomp']);
});

SimpleRouter::post('/character/{id}/deleteentry/{binary}', function($id, $binary) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);
    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    /** @var Character $character */
    CharacterController::EntryDelete($character, $binary);
});

SimpleRouter::post('/character/{id}/upload', function($id) {
    $character = EM::getInstance()->getRepository('MHFSaveManager\Model\Character')->find($id);

    if (!$character) {
        ResponseService::SendNotFound();
    }
    
    if ($_FILES["uploadBinary"]["error"] != UPLOAD_ERR_OK) {
        ResponseService::SendServerError();
    }
    
    /** @var Character $character */
    CharacterController::UploadSavedata($character);
});
