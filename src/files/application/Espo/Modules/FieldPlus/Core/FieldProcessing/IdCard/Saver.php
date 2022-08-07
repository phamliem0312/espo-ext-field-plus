<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\FieldPlus\Core\FieldProcessing\IdCard;

use Espo\Modules\FieldPlus\Entities\IdCard;
use Espo\Modules\FieldPlus\Repositories\IdCard as IdCardRepository;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Mapper\BaseMapper;

use Espo\Core\{
    ApplicationState,
    Utils\Metadata,
    FieldProcessing\Saver as SaverInterface,
    FieldProcessing\Saver\Params,
};

class Saver implements SaverInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    private $applicationState;

    private $accessChecker;

    private $metadata;

    public function __construct(
        EntityManager $entityManager,
        ApplicationState $applicationState,
        AccessChecker $accessChecker,
        Metadata $metadata
    ) {
        $this->entityManager = $entityManager;
        $this->applicationState = $applicationState;
        $this->accessChecker = $accessChecker;
        $this->metadata = $metadata;
    }

    public function process(Entity $entity, Params $params): void
    {
        $entityType = $entity->getEntityType();

        $defs = $this->entityManager->getDefs()->getEntity($entityType);

        if (!$defs->hasField('idCard')) {
            return;
        }

        if ($defs->getField('idCard')->getType() !== 'idCard') {
            return;
        }

        $idCardData = null;

        if ($entity->has('idCardData')) {
            $idCardData = $entity->get('idCardData');
        }

        if ($idCardData !== null) {
            $this->storeData($entity);

            return;
        }

        if ($entity->has('idCard')) {
            $this->storePrimary($entity);

            return;
        }
    }

    private function storeData(Entity $entity): void
    {
        $idCardValue = $entity->get('idCard');

        if (is_string($idCardValue)) {
            $idCardValue = trim($idCardValue);
        }

        $idCardData = null;

        if ($entity->has('idCardData')) {
            $idCardData = $entity->get('idCardData');
        }

        if (is_null($idCardData)) {
            return;
        }

        if (!is_array($idCardData)) {
            return;
        }

        $keyList = [];

        $keyPreviousList = [];

        $previousIdCardData = [];

        if (!$entity->isNew()) {
            /** @var IdCardRepository $repository */
            $repository = $this->entityManager->getRepository('IdCard');

            $previousIdCardData = $repository->getIdCardData($entity);
        }

        $hash = (object) [];
        $hashPrevious = (object) [];

        foreach ($idCardData as $row) {
            $key = trim($row->idCard);

            if (empty($key)) {
                continue;
            }

            if (isset($row->type)) {
                $type = $row->type;
            }
            else {
                $type = $this->metadata
                    ->get(['entityDefs', $entity->getEntityType(), 'fields', 'idCard', 'defaultType']);
            }

            $hash->$key = [
                'primary' => $row->primary ? true : false,
                'type' => $type,
                'optOut' => !empty($row->optOut) ? true : false,
                'invalid' => !empty($row->invalid) ? true : false,
            ];

            $keyList[] = $key;
        }

        if (
            $entity->has('idCardIsOptedOut')
            &&
            (
                $entity->isNew()
                ||
                (
                    $entity->hasFetched('idCardIsOptedOut')
                    &&
                    $entity->get('idCardIsOptedOut') !== $entity->getFetched('idCardIsOptedOut')
                )
            )
        ) {
            if ($idCardValue) {
                $key = $idCardValue;

                if (isset($hash->$key)) {
                    $hash->{$key}['optOut'] = $entity->get('idCardIsOptedOut');
                }
            }
        }

        foreach ($previousIdCardData as $row) {
            $key = $row->idCard;

            if (empty($key)) {
                continue;
            }

            $hashPrevious->$key = [
                'primary' => $row->primary ? true : false,
                'type' => $row->type,
                'optOut' => $row->optOut ? true : false,
                'invalid' => $row->invalid ? true : false,
            ];

            $keyPreviousList[] = $key;
        }

        $primary = false;

        $toCreateList = [];
        $toUpdateList = [];
        $toRemoveList = [];

        $revertData = [];

        foreach ($keyList as $key) {
            $data = $hash->$key;

            $new = true;
            $changed = false;

            if ($hash->{$key}['primary']) {
                $primary = $key;
            }

            if (property_exists($hashPrevious, $key)) {
                $new = false;

                $changed =
                    $hash->{$key}['type'] != $hashPrevious->{$key}['type'] ||
                    $hash->{$key}['optOut'] != $hashPrevious->{$key}['optOut'] ||
                    $hash->{$key}['invalid'] != $hashPrevious->{$key}['invalid'];

                if ($hash->{$key}['primary']) {
                    if ($hash->{$key}['primary'] == $hashPrevious->{$key}['primary']) {
                        $primary = false;
                    }
                }
            }

            if ($new) {
                $toCreateList[] = $key;
            }
            if ($changed) {
                $toUpdateList[] = $key;
            }
        }

        foreach ($keyPreviousList as $key) {
            if (!property_exists($hash, $key)) {
                $toRemoveList[] = $key;
            }
        }

        foreach ($toRemoveList as $number) {
            $idCard = $this->getByNumber($number);

            if (!$idCard) {
                continue;
            }

            $delete = $this->entityManager->getQueryBuilder()
                ->delete()
                ->from('EntityIdCard')
                ->where([
                    'entityId' => $entity->getId(),
                    'entityType' => $entity->getEntityType(),
                    'idCardId' => $idCard->getId(),
                ])
                ->build();

            $this->entityManager->getQueryExecutor()->execute($delete);
        }

        foreach ($toUpdateList as $number) {
            $idCard = $this->getByNumber($number);

            if ($idCard) {
                $skipSave = $this->checkChangeIsForbidden($idCard, $entity);

                if (!$skipSave) {
                    $idCard->set([
                        'type' => $hash->{$number}['type'],
                        'optOut' => $hash->{$number}['optOut'],
                        'invalid' => $hash->{$number}['invalid'],
                    ]);

                    $this->entityManager->saveEntity($idCard);
                }
                else {
                    $revertData[$number] = [
                        'type' => $idCard->get('type'),
                        'optOut' => $idCard->get('optOut'),
                        'invalid' => $idCard->get('invalid'),
                    ];
                }
            }
        }

        foreach ($toCreateList as $number) {
            $idCard = $this->getByNumber($number);

            if (!$idCard) {
                $idCard = $this->entityManager->getNewEntity('IdCard');

                $idCard->set([
                    'name' => $number,
                    'type' => $hash->{$number}['type'],
                    'optOut' => $hash->{$number}['optOut'],
                    'invalid' => $hash->{$number}['invalid'],
                ]);

                $this->entityManager->saveEntity($idCard);
            }
            else {
                $skipSave = $this->checkChangeIsForbidden($idCard, $entity);

                if (!$skipSave) {
                    if (
                        $idCard->get('type') != $hash->{$number}['type'] ||
                        $idCard->get('optOut') != $hash->{$number}['optOut'] ||
                        $idCard->get('invalid') != $hash->{$number}['invalid']
                    ) {
                        $idCard->set([
                            'type' => $hash->{$number}['type'],
                            'optOut' => $hash->{$number}['optOut'],
                            'invalid' => $hash->{$number}['invalid'],
                        ]);

                        $this->entityManager->saveEntity($idCard);
                    }
                }
                else {
                    $revertData[$number] = [
                        'type' => $idCard->get('type'),
                        'optOut' => $idCard->get('optOut'),
                        'invalid' => $idCard->get('invalid'),
                    ];
                }
            }

            $entityIdCard = $this->entityManager->getNewEntity('EntityIdCard');

            $entityIdCard->set([
                'entityId' => $entity->getId(),
                'entityType' => $entity->getEntityType(),
                'idCardId' => $idCard->getId(),
                'primary' => $number === $primary,
                'deleted' => false,
            ]);

            /** @var BaseMapper $mapper */
            $mapper = $this->entityManager->getMapper();

            $mapper->insertOnDuplicateUpdate($entityIdCard, [
                'primary',
                'deleted',
            ]);
        }

        if ($primary) {
            $idCard = $this->getByNumber($primary);

            if ($idCard) {
                $update1 = $this->entityManager
                    ->getQueryBuilder()
                    ->update()
                    ->in('EntityIdCard')
                    ->set(['primary' => false])
                    ->where([
                        'entityId' => $entity->getId(),
                        'entityType' => $entity->getEntityType(),
                        'primary' => true,
                        'deleted' => false,
                    ])
                    ->build();

                $this->entityManager->getQueryExecutor()->execute($update1);

                $update2 = $this->entityManager
                    ->getQueryBuilder()
                    ->update()
                    ->in('EntityIdCard')
                    ->set(['primary' => true])
                    ->where([
                        'entityId' => $entity->getId(),
                        'entityType' => $entity->getEntityType(),
                        'idCardId' => $idCard->getId(),
                        'deleted' => false,
                    ])
                    ->build();

                $this->entityManager->getQueryExecutor()->execute($update2);
            }
        }

        if (!empty($revertData)) {
            foreach ($idCardData as $row) {
                if (empty($revertData[$row->idCard])) {
                    continue;
                }

                $row->type = $revertData[$row->idCard]['type'];
                $row->optOut = $revertData[$row->idCard]['optOut'];
                $row->invalid = $revertData[$row->idCard]['invalid'];
            }

            $entity->set('idCardData', $idCardData);
        }
    }

    private function storePrimary(Entity $entity): void
    {
        if (!$entity->has('idCard')) {
            return;
        }

        $idCardValue = trim($entity->get('idCard'));

        $entityRepository = $this->entityManager->getRDBRepository($entity->getEntityType());

        if (!empty($idCardValue)) {
            if ($idCardValue !== $entity->getFetched('idCard')) {

                $idCardNew = $this->entityManager
                    ->getRDBRepository('idCard')
                    ->where([
                        'name' => $idCardValue,
                    ])
                    ->findOne();

                $isNewIdCard = false;

                if (!$idCardNew) {
                    $idCardNew = $this->entityManager->getNewEntity('idCard');

                    $idCardNew->set('name', $idCardValue);

                    if ($entity->has('idCardIsOptedOut')) {
                        $idCardNew->set('optOut', (bool) $entity->get('idCardIsOptedOut'));
                    }

                    $defaultType = $this->metadata
                        ->get('entityDefs.' .  $entity->getEntityType() . '.fields.idCard.defaultType');

                    $idCardNew->set('type', $defaultType);

                    $this->entityManager->saveEntity($idCardNew);

                    $isNewIdCard = true;
                }

                $idCardValueOld = $entity->getFetched('idCard');

                if (!empty($idCardValueOld)) {
                    $idCardOld = $this->getByNumber($idCardValueOld);

                    if ($idCardOld) {
                        $entityRepository->unrelate($entity, 'idCards', $idCardOld, [
                            'skipHooks' => true,
                        ]);
                    }
                }

                $entityRepository->relate($entity, 'idCards', $idCardNew, null, [
                    'skipHooks' => true,
                ]);

                if ($entity->has('idCardIsOptedOut')) {
                    $this->markNumberOptedOut($idCardValue, (bool) $entity->get('idCardIsOptedOut'));
                }

                $update = $this->entityManager
                    ->getQueryBuilder()
                    ->update()
                    ->in('EntityIdCard')
                    ->set(['primary' => true])
                    ->where([
                        'entityId' => $entity->getId(),
                        'entityType' => $entity->getEntityType(),
                        'idCardId' => $idCardNew->getId(),
                    ])
                    ->build();

                $this->entityManager->getQueryExecutor()->execute($update);

                return;

            }

            if (
                $entity->has('idCardIsOptedOut')
                &&
                (
                    $entity->isNew()
                    ||
                    (
                        $entity->hasFetched('idCardIsOptedOut')
                        &&
                        $entity->get('idCardIsOptedOut') !== $entity->getFetched('idCardIsOptedOut')
                    )
                )
            ) {
                $this->markNumberOptedOut($idCardValue, (bool) $entity->get('idCardIsOptedOut'));

                return;
            }

            return;
        }

        $idCardValueOld = $entity->getFetched('idCard');

        if (!empty($idCardValueOld)) {
            $idCardOld = $this->getByNumber($idCardValueOld);

            if ($idCardOld) {
                $entityRepository->unrelate($entity, 'idCards', $idCardOld, [
                    'skipHooks' => true,
                ]);
            }
        }
    }

    private function getByNumber(string $number): ?idCard
    {
        /** @var idCardRepository $repository */
        $repository = $this->entityManager->getRepository('idCard');

        return $repository->getByNumber($number);
    }

    private function markNumberOptedOut(string $number, bool $isOptedOut = true): void
    {
        /** @var idCardRepository $repository */
        $repository = $this->entityManager->getRepository('idCard');

        $repository->markNumberOptedOut($number, $isOptedOut);
    }

    private function checkChangeIsForbidden(idCard $idCard, Entity $entity): bool
    {
        if (!$this->applicationState->hasUser()) {
            return true;
        }

        $user = $this->applicationState->getUser();

        // @todo Check if not modifed by system.

        return !$this->accessChecker->checkEdit($user, $idCard, $entity);
    }
}
