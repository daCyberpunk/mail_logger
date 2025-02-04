<?php

namespace Pluswerk\MailLogger\Domain\Repository;

/***
 *
 * This file is part of an "+Pluswerk AG" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2018 Markus Hölzle <markus.hoelzle@pluswerk.ag>, +Pluswerk AG
 *
 ***/

use Pluswerk\MailLogger\Domain\Model\MailTemplate;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @method MailTemplate findOneByTypoScriptKey(string $key)
 */
class MailTemplateRepository extends Repository
{

    /**
     * @return void
     */
    public function initializeObject()
    {
        /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param string $typoScriptKey
     * @param int $languageUid
     * @return MailTemplate
     */
    public function findOneByTypoScriptKeyAndLanguage($typoScriptKey, $languageUid = null)
    {
        $mailTemplate = null;
        if ($languageUid === null) {
            $mailTemplate = $this->findOneByTypoScriptKey($typoScriptKey);
        } else {
            // Destroy session because the objects are stored there without language
            /** @var Session $session */
            $session = GeneralUtility::makeInstance(ObjectManager::class)->get(Session::class);
            $session->destroy();

            $query = $this->createQuery();
            $query->getQuerySettings()->setLanguageUid($languageUid);
            $query->matching($query->equals('typoScriptKey', $typoScriptKey));
            $query->setLimit(1);
            $mailTemplate = $query->execute(false)->getFirst();

            $session->destroy();
        }
        return $mailTemplate;
    }
}
