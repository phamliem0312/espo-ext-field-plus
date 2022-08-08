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

namespace Espo\Modules\FieldPlus\Core\Utils\Database\Orm\Fields;

class IdCard extends \Espo\Core\Utils\Database\Orm\Fields\Base
{
    /**
     * @param string $fieldName
     * @param string $entityType
     * @return array<string,mixed>
     */
    protected function load($fieldName, $entityType)
    {
        $foreignJoinAlias = "{$fieldName}{$entityType}{alias}Foreign";
        $foreignJoinMiddleAlias = "{$fieldName}{$entityType}{alias}ForeignMiddle";

        $mainFieldDefs = [
            'type' => 'varchar',
            'select' => [
                "select" => "idCards.name",
                'leftJoins' => [['idCard', 'idCards', ['primary' => 1]]],
            ],
            'selectForeign' => [
                "select" => "{$foreignJoinAlias}.name",
                'leftJoins' => [
                    [
                        'EntityIdCard',
                        $foreignJoinMiddleAlias,
                        [
                            "{$foreignJoinMiddleAlias}.entityId:" => "{alias}.id",
                            "{$foreignJoinMiddleAlias}.primary" => 1,
                            "{$foreignJoinMiddleAlias}.deleted" => 0,
                        ]
                    ],
                    [
                        'IdCard',
                        $foreignJoinAlias,
                        [
                            "{$foreignJoinAlias}.id:" => "{$foreignJoinMiddleAlias}.idCardId",
                            "{$foreignJoinAlias}.deleted" => 0,
                        ]
                    ]
                ],
            ],
            'fieldType' => 'idCard',
            'where' => [
                'LIKE' => [
                    'whereClause' => [
                        'id=s' => [
                            'from' => 'EntityIdCard',
                            'select' => ['entityId'],
                            'joins' => [
                                [
                                    'idCard',
                                    'idCard',
                                    [
                                        'idCard.id:' => 'idCardId',
                                        'idCard.deleted' => false,
                                    ],
                                ],
                            ],
                            'whereClause' => [
                                'deleted' => false,
                                'entityType' => $entityType,
                                'idCard.name*' => '{value}',
                            ],
                        ],
                    ],
                ],
                'NOT LIKE' => [
                    'whereClause' => [
                        'id!=s' => [
                            'from' => 'EntityIdCard',
                            'select' => ['entityId'],
                            'joins' => [
                                [
                                    'idCard',
                                    'idCard',
                                    [
                                        'idCard.id:' => 'idCardId',
                                        'idCard.deleted' => false,
                                    ],
                                ],
                            ],
                            'whereClause' => [
                                'deleted' => false,
                                'entityType' => $entityType,
                                'idCard.name*' => '{value}',
                            ],
                        ],
                    ],
                ],
                '=' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name=' => '{value}',
                    ],
                    'distinct' => true
                ],
                '<>' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name!=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'IN' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'NOT IN' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name!=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'IS NULL' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name=' => null,
                    ],
                    'distinct' => true
                ],
                'IS NOT NULL' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.name!=' => null,
                    ],
                    'distinct' => true
                ],
            ],
            'order' => [
                'order' => [
                    ['idCards.name', '{direction}'],
                ],
                'leftJoins' => [['idCards', 'idCards', ['primary' => 1]]],
                'additionalSelect' => ['idCards.name'],
            ],
        ];

        $numbericFieldDefs = [
            'type' => 'varchar',
            'notStorable' => true,
            'notExportable' => true,
            'where' => [
                'LIKE' => [
                    'whereClause' => [
                        'id=s' => [
                            'from' => 'EntityIdCard',
                            'select' => ['entityId'],
                            'joins' => [
                                [
                                    'idCard',
                                    'idCard',
                                    [
                                        'idCard.id:' => 'idCardId',
                                        'idCard.deleted' => false,
                                    ],
                                ],
                            ],
                            'whereClause' => [
                                'deleted' => false,
                                'entityType' => $entityType,
                                'idCard.numeric*' => '{value}',
                            ],
                        ],
                    ],
                ],
                'NOT LIKE' => [
                    'whereClause' => [
                        'id!=s' => [
                            'from' => 'EntityIdCard',
                            'select' => ['entityId'],
                            'joins' => [
                                [
                                    'idCard',
                                    'idCard',
                                    [
                                        'idCard.id:' => 'idCardId',
                                        'idCard.deleted' => false,
                                    ],
                                ]
                            ],
                            'whereClause' => [
                                'deleted' => false,
                                'entityType' => $entityType,
                                'idCard.numeric*' => '{value}',
                            ],
                        ],
                    ],
                ],
                '=' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric=' => '{value}',
                    ],
                    'distinct' => true
                ],
                '<>' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric!=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'IN' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'NOT IN' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric!=' => '{value}',
                    ],
                    'distinct' => true
                ],
                'IS NULL' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric=' => null,
                    ],
                    'distinct' => true
                ],
                'IS NOT NULL' => [
                    'leftJoins' => [['idCards', 'idCardsMultiple']],
                    'whereClause' => [
                        'idCardsMultiple.numeric!=' => null,
                    ],
                    'distinct' => true
                ],
            ],
        ];

        return [
            $entityType => [
                'fields' => [
                    $fieldName => $mainFieldDefs,
                    $fieldName . 'Data' => [
                        'type' => 'text',
                        'notStorable' => true,
                        'notExportable' => true,
                    ],
                    $fieldName .'IsOptedOut' => [
                        'type' => 'bool',
                        'notStorable' => true,
                        'select' => [
                            'select' => 'idCards.optOut',
                            'leftJoins' => [['idCards', 'idCards', ['primary' => 1]]],
                        ],
                        'selectForeign' => [
                            'select' => "{$foreignJoinAlias}.optOut",
                            'leftJoins' => [
                                [
                                    'EntityIdCard',
                                    $foreignJoinMiddleAlias,
                                    [
                                        "{$foreignJoinMiddleAlias}.entityId:" => "{alias}.id",
                                        "{$foreignJoinMiddleAlias}.primary" => 1,
                                        "{$foreignJoinMiddleAlias}.deleted" => 0,
                                    ]
                                ],
                                [
                                    'IdCard',
                                    $foreignJoinAlias,
                                    [
                                        "{$foreignJoinAlias}.id:" => "{$foreignJoinMiddleAlias}.idCardId",
                                        "{$foreignJoinAlias}.deleted" => 0,
                                    ]
                                ]
                            ],
                        ],
                        'where' => [
                            '= TRUE' => [
                                'whereClause' => [
                                    ['idCards.optOut=' => true],
                                    ['idCards.optOut!=' => null],
                                ],
                                'leftJoins' => [['idCards', 'idCards', ['primary' => 1]]],
                            ],
                            '= FALSE' => [
                                'whereClause' => [
                                    'OR' => [
                                        ['idCards.optOut=' => false],
                                        ['idCards.optOut=' => null],
                                    ]
                                ],
                                'leftJoins' => [['idCards', 'idCards', ['primary' => 1]]],
                            ]
                        ],
                       'order' => [
                            'order' => [
                                ['idCards.optOut', '{direction}'],
                            ],
                            'leftJoins' => [['idCards', 'idCards', ['primary' => 1]]],
                            'additionalSelect' => ['idCards.optOut'],
                        ],
                    ],
                    $fieldName . 'Numeric' => $numbericFieldDefs,
                ],
                'relations' => [
                    'idCards' => [
                        'type' => 'manyMany',
                        'entity' => 'IdCard',
                        'relationName' => 'entityIdCard',
                        'midKeys' => ['entityId', 'idCardId'],
                        'conditions' => [
                            'entityType' => $entityType,
                        ],
                        'additionalColumns' => [
                            'entityType' => [
                                'type' => 'varchar',
                                'len' => 100
                            ],
                            'primary' => [
                                'type' => 'bool',
                                'default' => false
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
