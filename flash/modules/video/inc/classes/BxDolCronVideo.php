<?php
/**
 * Copyright (c) BoonEx Pty Limited - http://www.boonex.com/
 * CC-BY License - http://creativecommons.org/licenses/by/3.0/
 */

require_once(BX_DIRECTORY_PATH_CLASSES . 'BxDolCron.php');
require_once(BX_DIRECTORY_PATH_CLASSES . 'BxDolAlerts.php');
require_once(BX_DIRECTORY_PATH_CLASSES . 'BxDolCategories.php');
require_once(BX_DIRECTORY_PATH_CLASSES . 'BxDolAlbums.php');

global $sModule;
$sModule = "video";

global $sIncPath;
global $sModulesPath;

require_once($sIncPath . "constants.inc.php");
require_once($sIncPath . "db.inc.php");
require_once($sIncPath . "xml.inc.php");
require_once($sIncPath . "functions.inc.php");
require_once($sIncPath . "apiFunctions.inc.php");
require_once($sIncPath . "customFunctions.inc.php");

global $sFilesPath;
$sModuleIncPath = $sModulesPath . $sModule . "/inc/";
require_once($sModuleIncPath . "header.inc.php");
require_once($sModuleIncPath . "constants.inc.php");
require_once($sModuleIncPath . "functions.inc.php");
require_once($sModuleIncPath . "customFunctions.inc.php");

class BxDolCronVideo extends BxDolCron
{
    function processing()
    {
        global $sModule;
        global $sFfmpegPath;
        global $sModulesPath;
        global $sFilesPath;

        $iFilesCount = getSettingValue($sModule, "processCount");
        if(!is_numeric($iFilesCount)) $iFilesCount = 2;
        $iFailedTimeout = getSettingValue($sModule, "failedTimeout");
        if(!is_numeric($iFailedTimeout)) $iFailedTimeout = 1;
        $iFailedTimeout *= 86400;
        $sDbPrefix = DB_PREFIX . ucfirst($sModule);

        $iCurrentTime = time();

        do {
            //remove all tokens older than 10 minutes
            if (!getResult("DELETE FROM `" . $sDbPrefix . "Tokens` WHERE `Date`<'" . ($iCurrentTime - 600). "'"))
                break;

            if (!getResult("UPDATE `" . $sDbPrefix . "Files` SET `Date`='" . $iCurrentTime . "', `Status`='" . STATUS_FAILED . "' WHERE `Status`='" . STATUS_PROCESSING . "' AND `Date`<'" . ($iCurrentTime - $iFailedTimeout) . "'"))
                break;
            $rResult = getResult("SELECT * FROM `" . $sDbPrefix . "Files` WHERE `Status`='" . STATUS_PENDING . "' ORDER BY `ID` LIMIT " . $iFilesCount);
            if (!$rResult)
                break;
            for($i=0; $i<mysql_num_rows($rResult); $i++) {
                $aFile = mysql_fetch_assoc($rResult);
                if(convertVideo($aFile['ID'])) {
                    $sType = 'bx_videos';
                    //album counter & cover update
                    if(getSettingValue($sModule, "autoApprove") == TRUE_VAL) {
                        $oAlbum = new BxDolAlbums($sType);
                        $oAlbum->updateObjCounterById($aFile['ID']);
                        if (getParam($oAlbum->sAlbumCoverParam) == 'on')
                            $oAlbum->updateLastObjById($aFile['ID']);
                    }
                    //tags & categories parsing
                    $oTag = new BxDolTags();
                    $oTag->reparseObjTags($sType, $aFile['ID']);

                    $oCateg = new BxDolCategories($aFile['Owner']);
                    $oCateg->reparseObjTags($sType, $aFile['ID']);
                } else
                    if(!getResult("UPDATE `" . $sDbPrefix . "Files` SET `Status`='" . STATUS_FAILED . "' WHERE `ID`='" . $aFile['ID'] . "'"))
                        break;
            }
        } while(false);
    }
}
