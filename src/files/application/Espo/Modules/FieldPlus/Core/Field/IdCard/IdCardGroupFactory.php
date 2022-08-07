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

namespace Espo\Modules\FieldPlus\Core\Field\IdCard;

use Espo\Modules\FieldPlus\Repositories\IdCard as Repository;

use Espo\ORM\{
    EntityManager,
    Entity,
    Value\ValueFactory,
};

use Espo\Core\Utils\Metadata;

use Espo\Modules\FieldPlus\Core\{
    Field\IdCardGroup,
    Field\IdCard,
};

use RuntimeException;

/**
 * A phone number group factory.
 */
class IdCardGroupFactory implements ValueFactory
{
    private Metadata $metadata;

    private EntityManager $entityManager;

    /**
     * @todo Use OrmDefs instead of Metadata.
     */
    public function __construct(Metadata $metadata, EntityManager $entityManager)
    {
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function isCreatableFromEntity(Entity $entity, string $field): bool
    {
        $type = $this->metadata->get([
            'entityDefs', $entity->getEntityType(), 'fields', $field, 'type'
        ]);

        if ($type !== 'idCard') {
            return false;
        }

        return true;
    }

    public function createFromEntity(Entity $entity, string $field): IdCardGroup
    {
        if (!$this->isCreatableFromEntity($entity, $field)) {
            throw new RuntimeException();
        }

        $idCardList = [];

        $primaryIdCard = null;

        $dataList = null;

        $dataAttribute = $field . 'Data';

        if ($entity->has($dataAttribute)) {
            $dataList = $this->sanitizeDataList(
                $entity->get($dataAttribute)
            );
        }

        if (!$dataList && $entity->has($field) && !$entity->get($field)) {
            $dataList = [];
        }

        if (!$dataList) {
            /** @var Repository $repository */
            $repository = $this->entityManager->getRepository('IdCard');

            $dataList = $repository->getIdCardData($entity);
        }

        foreach ($dataList as $item) {
            $idCard = IdCard::create($item->idCard);

            if ($item->type) {
                $idCard = $idCard->withType($item->type);
            }

            if ($item->optOut) {
                $idCard = $idCard->optedOut();
            }

            if ($item->invalid) {
                $idCard = $idCard->invalid();
            }

            if ($item->primary) {
                $primaryIdCard = $idCard;
            }

            $idCardList[] = $idCard;
        }

        $group = IdCardGroup::create($idCardList);

        if ($primaryIdCard) {
            $group = $group->withPrimary($primaryIdCard);
        }

        return $group;
    }

    /**
     * @param array<int,array<string,mixed>|\stdClass> $dataList
     * @return \stdClass[]
     */
    private function sanitizeDataList(array $dataList): array
    {
        $sanitizedDataList = [];

        foreach ($dataList as $item) {
            if (is_array($item)) {
                $sanitizedDataList[] = (object) $item;

                continue;
            }

            if (!is_object($item)) {
                throw new RuntimeException("Bad data.");
            }

            $sanitizedDataList[] = $item;
        }

        return $sanitizedDataList;
    }
}
