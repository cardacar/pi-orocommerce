<?php

namespace Oro\Bundle\ImapBundle\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\ImapBundle\Connector\ImapConnector;
use Oro\Bundle\ImapBundle\Entity\ImapEmailFolder;
use Oro\Bundle\ImapBundle\Form\Model\EmailFolderModel;
use Oro\Bundle\ImapBundle\Mail\Storage\Folder;

/**
 * Allows to fetch and manipulate IMAP email folders.
 */
class ImapEmailFolderManager
{
    private ImapConnector $connector;
    private EntityManagerInterface $em;
    private EmailOrigin $origin;

    public function __construct(ImapConnector $connector, EntityManagerInterface $em, EmailOrigin $origin)
    {
        $this->connector = $connector;
        $this->em = $em;
        $this->origin = $origin;
    }

    /**
     * @return ArrayCollection|EmailFolder[]
     */
    public function getFolders(): ArrayCollection
    {
        // retrieve folders from imap
        $folders = $this->connector->findFolders();

        // transform folders into tree of models
        $emailFolderModels = $this->processFolders($folders);

        if ($this->origin->getId()) {
            // renew existing folders if origin already exists
            $existingFolders = $this->getExistingFolders();

            // merge synced and existing folders
            $emailFolderModels = $this->mergeFolders($emailFolderModels, $existingFolders);
            // mark old folders as outdated
            $this->markAsOutdated($existingFolders);

            // flush changes
            $this->em->flush();
        }

        return $this->extractEmailFolders($emailFolderModels);
    }

    /**
     * Gets UIDVALIDITY of the given folder
     *
     * @param EmailFolder|Folder|string $folder
     *
     * @return int|null
     */
    public function getUidValidity($folder)
    {
        if ($folder instanceof Folder) {
            $folderName = $folder->getGlobalName();
        } elseif ($folder instanceof EmailFolder) {
            $folderName = $folder->getFullName();
        } elseif (is_string($folder)) {
            $folderName = $folder;
        }

        if (!isset($folderName)) {
            throw new \RuntimeException('Invalid argument passed to getUidValidity method');
        }

        try {
            $this->connector->selectFolder($folderName);

            return $this->connector->getUidValidity();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param ArrayCollection|ImapEmailFolder[] $existingFolders
     */
    private function markAsOutdated(ArrayCollection $existingFolders): void
    {
        $outdatedAt = new \DateTime('now', new \DateTimeZone('UTC'));
        foreach ($existingFolders as $existingFolder) {
            $emailFolder = $existingFolder->getFolder();
            $emailFolder->setOutdatedAt($outdatedAt);
            $emailFolder->setSyncEnabled(false);
        }
    }

    /**
     * @param Folder[] $srcFolders
     *
     * @return ArrayCollection|EmailFolderModel[]
     */
    private function processFolders(array $srcFolders): ArrayCollection
    {
        $emailFolderModels = new ArrayCollection();
        foreach ($srcFolders as $srcFolder) {
            $emailFolderModel = null;
            $uidValidity = $this->getUidValidity($srcFolder);

            if ($uidValidity !== null) {
                $emailFolderModel = $this->createEmailFolderModel($srcFolder, $uidValidity);
                $emailFolderModels->add($emailFolderModel);
            }

            $childSrcFolders = [];
            foreach ($srcFolder as $childSrcFolder) {
                $childSrcFolders[] = $childSrcFolder;
            }

            $childFolders = $this->processFolders($childSrcFolders);
            if ($emailFolderModel !== null) {
                foreach ($childFolders as $childFolder) {
                    $emailFolderModel->addSubFolderModel($childFolder);
                }
            } else {
                foreach ($childFolders as $childFolder) {
                    $emailFolderModels->add($childFolder);
                }
            }
        }

        return $emailFolderModels;
    }

    /**
     * @param ArrayCollection|EmailFolderModel[] $syncedFolderModels
     * @param ArrayCollection|ImapEmailFolder[]  $existingImapFolders
     *
     * @return ArrayCollection|EmailFolderModel[]
     */
    private function mergeFolders(
        ArrayCollection $syncedFolderModels,
        ArrayCollection $existingImapFolders
    ): ArrayCollection {
        foreach ($syncedFolderModels as $syncedFolderModel) {
            $f = $existingImapFolders->filter(function (ImapEmailFolder $imapEmailFolder) use ($syncedFolderModel) {
                return $imapEmailFolder->getUidValidity() === $syncedFolderModel->getUidValidity();
            });

            if ($f->isEmpty()) {
                // there is a new folder on server, create it
                $imapEmailFolder = $this->createImapEmailFolder($syncedFolderModel);

                // persist ImapEmailFolder and (by cascade) EmailFolder
                $this->em->persist($imapEmailFolder);
            } else {
                /** @var ImapEmailFolder $existingImapFolder */
                $existingImapFolder = $f->first();
                $emailFolder = $existingImapFolder->getFolder();
                $this->em->refresh($emailFolder);
                $emailFolder->setName($syncedFolderModel->getEmailFolder()->getName());
                $emailFolder->setFullName($syncedFolderModel->getEmailFolder()->getFullName());
                $emailFolder->setType($syncedFolderModel->getEmailFolder()->getType());
                $emailFolder->setSynchronizedAt($syncedFolderModel->getEmailFolder()->getSynchronizedAt());
                $syncedFolderModel->setEmailFolder($emailFolder);

                $existingImapFolders->removeElement($existingImapFolder);
            }

            if ($syncedFolderModel->hasSubFolderModels()) {
                $syncedFolderModel->setSubFolderModels(
                    $this->mergeFolders(
                        $syncedFolderModel->getSubFolderModels(),
                        $existingImapFolders
                    )
                );
            }
        }

        return $syncedFolderModels;
    }

    /**
     * @param Folder          $srcFolder
     * @param int|string|null $uidValidity
     *
     * @return EmailFolderModel
     */
    private function createEmailFolderModel(Folder $srcFolder, $uidValidity): EmailFolderModel
    {
        $folder = new EmailFolder();
        $folder
            ->setFullName($srcFolder->getGlobalName())
            ->setName($srcFolder->getLocalName())
            ->setType($srcFolder->guessFolderType())
            ->setOrigin($this->origin);

        $emailFolderModel = new EmailFolderModel();
        $emailFolderModel->setUidValidity($uidValidity);
        $emailFolderModel->setEmailFolder($folder);

        return $emailFolderModel;
    }

    private function createImapEmailFolder(EmailFolderModel $emailFolderModel): ImapEmailFolder
    {
        $imapEmailFolder = new ImapEmailFolder();
        $emailFolder = $emailFolderModel->getEmailFolder();
        $imapEmailFolder->setFolder($emailFolder);
        if ($emailFolderModel->hasParentFolderModel()) {
            $emailFolder->setParentFolder($emailFolderModel->getParentFolderModel()->getEmailFolder());
        }

        $imapEmailFolder->setUidValidity($emailFolderModel->getUidValidity());

        return $imapEmailFolder;
    }

    /**
     * @param ArrayCollection|EmailFolderModel[] $emailFolderModels
     *
     * @return ArrayCollection|EmailFolder[]
     */
    private function extractEmailFolders(ArrayCollection $emailFolderModels): ArrayCollection
    {
        $emailFolders = new ArrayCollection();
        foreach ($emailFolderModels as $emailFolderModel) {
            $emailFolder = $emailFolderModel->getEmailFolder();
            if ($emailFolderModel->hasSubFolderModels()) {
                $emailFolder->setSubFolders($this->extractEmailFolders($emailFolderModel->getSubFolderModels()));
            } else {
                $emailFolder->setSubFolders([]);
            }
            $emailFolders->add($emailFolder);
        }

        return $emailFolders;
    }

    /**
     * @return ArrayCollection|ImapEmailFolder[]
     */
    private function getExistingFolders(): ArrayCollection
    {
        $qb = $this->em->createQueryBuilder()
            ->select('ief')
            ->from(ImapEmailFolder::class, 'ief')
            ->leftJoin('ief.folder', 'ef')
            ->where('ef.origin = :origin')
            ->setParameter('origin', $this->origin);

        return new ArrayCollection($qb->getQuery()->getResult());
    }
}
