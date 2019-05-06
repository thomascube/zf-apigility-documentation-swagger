<?php

/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2018 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Apigility\Documentation\Swagger;

use ZF\Apigility\Documentation\Field;
use ZF\Apigility\Documentation\Operation;
use ZF\Apigility\Documentation\Service as BaseService;
use ZF\Apigility\Documentation\Swagger\Model\ModelGenerator;

class Service extends BaseService
{
    const DEFAULT_TYPE = 'string';
    const ARRAY_TYPE = 'array';
    const DATE_TYPE = 'date-time';
    const NUMBER_TYPE = 'number';
    const NUMBER_TYPES = ['integer', 'float'];

    static protected $HalJsonLinks = [
        'type' => 'object',
        'properties' => [
            'self' => [
                'type' => 'object',
                'description' => 'Link relation to the current page of the collection',
                'properties' => [
                    'href' => [
                        'type' => 'string',
                        'description' => '',
                    ],
                ],
            ],
            'first' => [
                'type' => 'object',
                'description' => 'Link relation to the first page of the collection',
                'properties' => [
                    'href' => [
                        'type' => 'string',
                        'description' => '',
                    ],
                ],
            ],
            'last' => [
                'type' => 'object',
                'description' => 'Link relation to the last page of the collection',
                'properties' => [
                    'href' => [
                        'type' => 'string',
                        'description' => '',
                    ],
                ],
            ],
            'next' => [
                'type' => 'object',
                'description' => 'Link relation to the next page of the collection',
                'properties' => [
                    'href' => [
                        'type' => 'string',
                        'description' => '',
                    ],
                ],
            ],
            'prev' => [
                'type' => 'object',
                'description' => 'Link relation to the previous page of the collection',
                'properties' => [
                    'href' => [
                        'type' => 'string',
                        'description' => '',
                    ],
                ],
            ],
        ],
    ];

    static protected $haljsoncounts = [
        'total_items' => [
            'type' => 'number',
            'format' => 'integer',
            'description' => 'Number of records found',
        ],
        'page_count' => [
            'type' => 'number',
            'format' => 'integer',
            'description' => 'Number of pages in result set',
        ],
        'page_size' => [
            'type' => 'number',
            'format' => 'integer',
            'description' => 'Number of records listed per page',
        ],
        'page' => [
            'type' => 'number',
            'format' => 'integer',
            'description' => 'Current page',
            'default' => 1,
        ],
    ];

    /**
     * @var BaseService
     */
    protected $service;

    /**
     * @var ModelGenerator
     */
    protected $modelGenerator;

    /**
     * @var array
     */
    protected $halJsonCollections = [];

    /**
     * @param BaseService $service
     */
    public function __construct(BaseService $service)
    {
        $this->service = $service;
        $this->modelGenerator = new ModelGenerator();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->cleanEmptyValues([
            'tags' => $this->getTags(),
            'paths' => $this->cleanEmptyValues($this->getPaths()),
            'definitions' => $this->getDefinitions()
        ]);
    }

    /**
     * @return array
     */
    protected function getTags()
    {
        return [
            $this->cleanEmptyValues([
                'name' => $this->service->getName(),
                'description' => $this->service->getDescription(),
            ])
        ];
    }

    /**
     * @return array
     */
    protected function getPaths()
    {
        $route = $this->getRouteWithReplacements();
        if ($this->isRestService()) {
            return $this->getRestPaths($route);
        }
        return $this->getOtherPaths($route);
    }

    /**
     * @return string
     */
    protected function getRouteWithReplacements()
    {
        // routes and parameter mangling ([:foo] will become {foo}
        return preg_replace('#\[?\/:(\w+)\]?#', '/{$1}', $this->service->route);
    }

    /**
     * @return bool
     */
    protected function isRestService()
    {
        return ($this->service->routeIdentifierName);
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getRestPaths($route)
    {
        $entityOperations = $this->getEntityOperationsData($route);
        $collectionOperations = $this->getCollectionOperationsData($route);
        $collectionPath = str_replace('/{' . $this->service->routeIdentifierName . '}', '', $route);
        if ($collectionPath === $route) {
            return [
                $collectionPath => array_merge($collectionOperations, $entityOperations)
            ];
        }
        return [
            $collectionPath => $collectionOperations,
            $route => $entityOperations
        ];
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getOtherPaths($route)
    {
        $operations = $this->getOtherOperationsData($route);
        return [$route => $operations];
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getEntityOperationsData($route)
    {
        $urlParameters = $this->getURLParametersRequired($route);
        $operations = $this->service->getEntityOperations();
        return $this->getOperationsData($operations, $urlParameters, false);
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getCollectionOperationsData($route)
    {
        $urlParameters = $this->getURLParametersNotRequired($route);
        $urlParameters += $this->getCollectionQueryParameters();
        unset($urlParameters[$this->service->routeIdentifierName]);
        $operations = $this->service->operations;
        return $this->getOperationsData($operations, $urlParameters, true);
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getOtherOperationsData($route)
    {
        $urlParameters = $this->getURLParametersRequired($route);
        $operations = $this->service->operations;
        return $this->getOperationsData($operations, $urlParameters);
    }

    /**
     * @param string $route
     * @param array $urlParameters
     * @param boolean $isCollection
     * @return array
     */
    protected function getOperationsData($operations, $urlParameters, $isCollection = null)
    {
        $operationsData = [];
        foreach ($operations as $operation) {
            $method = $this->getMethodFromOperation($operation);
            $parameters = array_values($urlParameters);
            if ($this->isMethodPostPutOrPatch($method)) {
                $parameters[] = $this->getPostPatchPutBodyParameter();
            }
            $pathOperation = $this->getPathOperation($operation, $parameters, $isCollection);
            $operationsData[$method] = $pathOperation;
        }
        return $operationsData;
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getURLParametersRequired($route)
    {
        return $this->getURLParameters($route, true);
    }

    /**
     * @param string $route
     * @return array
     */
    protected function getURLParametersNotRequired($route)
    {
        return $this->getURLParameters($route, false);
    }

    /**
     * @param string $route
     * @param bool $required
     * @return array
     */
    protected function getURLParameters($route, $required)
    {
        // find all parameters in Swagger naming format
        preg_match_all('#{([\w\d_-]+)}#', $route, $parameterMatches);

        $templateParameters = [];
        foreach ($parameterMatches[1] as $paramSegmentName) {
            $templateParameters[$paramSegmentName] = [
                'in' => 'path',
                'name' => $paramSegmentName,
                'description' => 'URL parameter ' . $paramSegmentName,
                'type' => 'string',
                'required' => $required,
                // 'minimum' => 0,
                // 'maximum' => 1
            ];
        }
        return $templateParameters;
    }

    protected function getCollectionQueryParameters()
    {
        $queryParameters = [];
        foreach ($this->service->getFields('query') as $field) {
            $paramName = $field->getName();
            $queryParameters[$paramName] = [
                'in' => 'query',
                'name' => $paramName,
                'description' => $field->getDescription() ?? 'Query parameter ' . $paramName,
                'type' => $field->getFieldType() ?: ($field->getType() ?: 'string'),
                'required' => $field->isRequired(),
            ];
        }

        return $queryParameters;
    }

    /**
     * @return array
     */
    protected function getPostPatchPutBodyParameter()
    {
        return [
            'in' => 'body',
            'name' => 'body',
            'required' => true,
            'schema' => [
                '$ref' => '#/definitions/' . $this->service->getName() . 'Input',
            ]
        ];
    }

    /**
     * @param string $method
     * @return bool
     */
    protected function isMethodPostPutOrPatch($method)
    {
        return in_array(strtolower($method), ['post', 'put', 'patch']);
    }

    /**
     * @param Operation $operation
     * @return string
     */
    protected function getMethodFromOperation(Operation $operation)
    {
        return strtolower($operation->getHttpMethod());
    }

    /**
     * @param Operation $operation
     * @param array $parameters
     * @param boolean $isCollection
     * @return array
     */
    protected function getPathOperation(Operation $operation, $parameters, $isCollection = null)
    {
        return $this->cleanEmptyValues([
            'tags' => [$this->service->getName()],
            'description' => $operation->getDescription(),
            'parameters' => $parameters,
            'produces' => $this->service->getRequestAcceptTypes(),
            'responses' => $this->getResponsesFromOperation($operation, $isCollection),
            'security' => $operation->requiresAuthorization() ? $this->getSecurityData() : null,
        ]);
    }

    /**
     * @param Operation $operation
     * @param boolean $isCollection
     * @return array
     */
    protected function getResponsesFromOperation(Operation $operation, $isCollection = null)
    {
        $responses = [];
        $responseStatusCodes = $operation->getResponseStatusCodes();
        foreach ($responseStatusCodes as $responseStatusCode) {
            $code = intval($responseStatusCode['code']);
            $responses[$code] = $this->cleanEmptyValues([
                'description' => $responseStatusCode['message'],
                'schema' => $this->getResponseSchema($operation, $code, $isCollection),
            ]);
        }
        return $responses;
    }

    /**
     * @param Operation $operation
     * @param int $code
     * @param boolean $isCollection
     * @return null|array If the return code is neither 200 or 201, returns null.
     *     Otherwise, it retrieves the response description, passes it to the
     *     model generator, and uses the returned value.
     */
    protected function getResponseSchema(Operation $operation, $code, $isCollection = null)
    {
        if ($code === 200 || $code === 201) {
            $schema = $this->modelGenerator->generate($operation->getResponseDescription());

            if (!$schema) {
                // refer to entity difinition if available
                $serviceName = $this->service->getName();
                $definitionName = $serviceName . 'Entity';
                $entityDefinitions = $this->getEntityDefinitions();
                if (isset($entityDefinitions[$definitionName])) {
                    $schema = [
                        '$ref' => '#/definitions/' . $definitionName,
                    ];
                }

                // define HAL+JSON collection model for this collection type
                if (isset($schema['$ref']) && $isCollection && $code === 200) {
                    // skip if hal+json is not listed as accept type
                    if (!in_array('application/hal+json', $this->service->getRequestAcceptTypes())) {
                        return null;
                    }

                    $docsArray = $this->service->getDocs();
                    $halJsonType = $serviceName . 'HalCollection';

                    if (isset($docsArray['zf-rest']['collection_name'])) {
                        $collectionName = $docsArray['zf-rest']['collection_name'];
                    } else {
                        $collectionName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $serviceName));
                    }

                    $this->halJsonCollections[$halJsonType] = [
                        'description' => 'HAL+JSON formatted collection of records. The Hypertext Application Language (HAL) is documented at http://stateless.co/hal_specification.html',
                        'properties' => [
                            '_links' => [
                                'type' => 'object',
                                'schema' => [
                                    '$ref' => '#/definitions/HalJsonLinks',
                                ]
                            ],
                            '_embedded' => [
                                'type' => 'object',
                                'description' => 'HAL+JSON formatted collection of models',
                                'properties' => [
                                    $collectionName => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/definitions/' . $definitionName,
                                        ],
                                    ],
                                ],
                            ],
                        ] + self::$haljsoncounts,
                    ];
                    $schema = [
                        '$ref' => '#/definitions/' . $halJsonType,
                    ];
                }
            }

            return $schema;
        }
    }

    /**
     * @return array
     */
    protected function getDefinitions()
    {
        return array_merge($this->getEntityDefinitions(), $this->getInputDefinitions(), $this->getHalJsonDefinitions());
    }

    /**
     * @return array
     */
    protected function getEntityDefinitions()
    {
        $fields = $this->getEntityFields();
        if (empty($fields)) {
            return [];
        }

        $model = $this->getModelFromFields($fields);
        $definitionName = $this->service->getName() . 'Entity';
        return [$definitionName => $model];
    }

    /**
     * @return array
     */
    protected function getInputDefinitions()
    {
        if (! $this->serviceContainsPostPutOrPatchMethod()) {
            return [];
        }
        $modelFromFields = $this->getModelFromFields($this->getInputFilterFields());
        $modelFromPostDescription = $this->getModelFromFirstPostDescription();
        $model = array_replace_recursive($modelFromFields, $modelFromPostDescription);
        $definitionName = $this->service->getName() . 'Input';
        return [$definitionName => $model];
    }

    protected function getHalJsonDefinitions()
    {
        if (!empty($this->halJsonCollections)) {
            return $this->halJsonCollections + ['HalJsonLinks' => self::$HalJsonLinks];
        }

        return [];
    }

    /**
     * @return bool
     */
    protected function serviceContainsPostPutOrPatchMethod()
    {
        foreach ($this->getAllOperations() as $operation) {
            $method = $this->getMethodFromOperation($operation);
            if ($this->isMethodPostPutOrPatch($method)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array
     * @return array
     */
    protected function getModelFromFields(array $fields)
    {
        $required = $properties = [];

        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $properties[$field->getName()] = $this->getFieldProperties($field);
            if ($field->isRequired()) {
                $required[] = $field->getName();
            }
        }

        return $this->cleanEmptyValues([
            'type' => 'object',
            'properties' => !empty($properties) ? $properties : null,
            'required' => !empty($required) ? $required : null,
        ]);
    }

    /**
     * @return array
     */
    protected function getModelFromFirstPostDescription()
    {
        $firstPostDescription = $this->getFirstPostRequestDescription();
        if (! $firstPostDescription) {
            return [];
        }
        return $this->modelGenerator->generate($firstPostDescription) ?: [];
    }

    /**
     * @return null|mixed Returns null if no POST operations are discovered;
     *     otherwise, returns the request description from the first POST
     *     operation discovered.
     */
    protected function getFirstPostRequestDescription()
    {
        foreach ($this->getAllOperations() as $operation) {
            $method = $this->getMethodFromOperation($operation);
            if ($method === 'post') {
                return $operation->getRequestDescription();
            }
        }
        return null;
    }

    /**
     * @return null|array
     */
    protected function getInputFilterFields()
    {
        // Fields are part of the default input filter when present.
        $fields = $this->service->fields;
        if (isset($fields['input_filter'])) {
            return $fields['input_filter'];
        }
        return [];
    }

    /**
     * @return null|array
     */
    protected function getEntityFields()
    {
        $fields = $this->service->fields;
        if (isset($fields['entity'])) {
            return $fields['entity'];
        }
        return [];
    }

    /**
     * @param Field $field
     * @return array
     */
    protected function getFieldProperties(Field $field)
    {
        $type = $this->getFieldType($field);
        $properties = [];
        $properties['type'] = $type;
        if ($type === self::ARRAY_TYPE) {
            $properties['items'] = ['type' => $field->getType() ?: self::DEFAULT_TYPE];
        } else if ($type === self::DATE_TYPE) {
            $properties['type'] = self::DEFAULT_TYPE;
            $properties['format'] = $type;
        } else if (in_array($type, self::NUMBER_TYPES)) {
            $properties['type'] = self::NUMBER_TYPE;
            $properties['format'] = $type;
        }
        $properties['description'] = $field->getDescription();
        return $this->cleanEmptyValues($properties);
    }

    /**
     * @param Field $field
     * @return string
     */
    protected function getFieldType(Field $field)
    {
        return method_exists($field, 'getFieldType') && ! empty($field->getFieldType())
            ? $field->getFieldType()
            : self::DEFAULT_TYPE;
    }

    /**
     * @return array
     */
    protected function getAllOperations()
    {
        $entityOperations = $this->service->getEntityOperations();
        if (is_array($entityOperations)) {
            return array_merge($this->service->getOperations(), $this->service->getEntityOperations());
        }
        return $this->service->getOperations();
    }

    /**
     * @param array $data
     * @return array $data omitting empty values
     */
    protected function cleanEmptyValues(array $data)
    {
        return array_filter($data, function ($item) {
            return ! empty($item);
        });
    }

    /**
     * @return string|null
     */
    protected function getSecurityData()
    {
        $docsArray = $this->service->getDocs();
        if (isset($docsArray['security'])) {
            $scopes = (array)($docsArray['scope'] ?? []);
            $values = is_array($docsArray['security']) ? $docsArray['security'] : [$docsArray['security']];
            return array_map(function($key) use ($scopes) { return [$key => $scopes]; }, $values);
        }

        return null;
    }
}
