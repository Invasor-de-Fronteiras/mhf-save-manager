<?php

namespace MHFSaveManager\Controller;

use Doctrine\Common\Collections\Criteria;
use MHFSaveManager\Database\EM;
use MHFSaveManager\Model\NormalShopItem;
use MHFSaveManager\Service\ResponseService;

class RoadShopController extends AbstractController
{
    public static function Index()
    {
        $roadItems = EM::getInstance()->getRepository('MHFSaveManager\Model\NormalShopItem')->matching(
            Criteria::create()->where(Criteria::expr()->eq('shoptype', '10'))
        )->toArray();

        include_once ROOT_DIR . '/app/Views/roadshop.php';
    }
    
    public static function EditRoadShopItem()
    {
        $item = new NormalShopItem();
    
        if (isset($_POST['id']) && $_POST['id'] > 0) {
            $item = EM::getInstance()->getRepository('MHFSaveManager\Model\NormalShopItem')->find($_POST['id']);
        } else {
            $item->setItemhash(EM::getInstance()->getRepository('MHFSaveManager\Model\NormalShopItem')->matching(
                    Criteria::create()->orderBy(['itemhash' => 'desc']))->first()->getItemhash()+1);
            EM::getInstance()->persist($item);
        }
    
        $item->setItemid(hexdec(self::numberConvertEndian(hexdec($_POST['item']), 2)));
        $item->setBoughtquantity($_POST['boughtQuantity']);
        $item->setMaximumquantity($_POST['maximumQuantity']);
        $item->setTradequantity($_POST['tradeQuantity']);
        $item->setRankreqg($_POST['grank']);
        $item->setPoints($_POST['cost']);
        $item->setShopid($_POST['category']);
        $item->setRoadfloorsrequired($_POST['roadFloors']);
        $item->setWeeklyfataliskills($_POST['fatalis']);
        
        $item->setShoptype(10);
        $item->setRankreqlow(0);
        $item->setRankreqhigh(0);
        $item->setStorelevelreq(1);
        
        EM::getInstance()->flush();
        
        ResponseService::SendOk($item->getItemhash());
    }
    
    public static function ExportRoadShopItems()
    {
        $records = EM::getInstance()->getRepository('MHFSaveManager\Model\NormalShopItem')->matching(
        Criteria::create()->where(Criteria::expr()->eq('shoptype', '10')));
        self::arrayOfModelsToCSVDownload($records, "RoadShopItems");
    }
    
    public static function ImportRoadShopItems()
    {
        self::importFromCSV('roadShopCSV', NormalShopItem::class, 'delete from MHFSaveManager\Model\NormalShopItem n where n.shoptype = 10');
        
        exit();
        $lines = preg_split('/\r\n|\r|\n/',  file_get_contents($_FILES["roadShopCSV"]["tmp_name"]));
        $attributes = str_getcsv($lines[0]);
        unset($lines[0]);
        $em = EM::getInstance();
        foreach ($lines as $line) {
            if ($line == "") {
                continue;
            }
            
            $lineValues = str_getcsv($line);
            $item = new NormalShopItem();
            foreach ($attributes as $key => $attribute) {
                $setter = "set".ucfirst($attribute);
                $item->$setter($lineValues[$key]);
            }
            $em->persist($item);
        }
        $em->createQuery('delete from MHFSaveManager\Model\NormalShopItem n where n.shoptype = 10')->execute();
        $em->flush();
        
        ResponseService::SendOk();
    }
}
