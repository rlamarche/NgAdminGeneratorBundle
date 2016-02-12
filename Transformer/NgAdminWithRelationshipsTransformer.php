<?php

namespace marmelab\NgAdminGeneratorBundle\Transformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping\ClassMetadata;
use marmelab\NgAdminGeneratorBundle\Guesser\ReferencedFieldGuesser;

class NgAdminWithRelationshipsTransformer implements TransformerInterface
{
    private $metadataFactory;
    private $referencedFieldGuesser;

    public function __construct(EntityManagerInterface $entityManager, ReferencedFieldGuesser $referencedFieldGuesser)
    {
        $this->metadataFactory = $entityManager->getMetadataFactory();
        $this->referencedFieldGuesser = $referencedFieldGuesser;
    }

    public function transform($entities)
    {
        $entitiesByClass = [];
        foreach ($entities as $data) {
            $entitiesByClass[$data['class']] = $data;
        }

        foreach ($entities as &$transformedConfiguration) {
            $associationMappings = $this->metadataFactory->getMetadataFor($transformedConfiguration['class'])->getAssociationMappings();
            $transformedConfiguration['has_relationships'] = (bool)count($associationMappings);
            if (!count($associationMappings)) {
                return $transformedConfiguration;
            }

            foreach ($associationMappings as $fieldName => $associationMapping) {
                // Try to find field to modify
                $fieldIndex = $this->getFieldIndex($transformedConfiguration['fields'], Inflector::tableize($fieldName));
                if (!$fieldIndex) {
                    // if not found, try with referenced column
                    $fieldName = $associationMapping['joinColumns'][0]['name'];
                    $fieldIndex = $this->getFieldIndex($transformedConfiguration['fields'], $fieldName);
                    if (!$fieldIndex) {
                        continue;
                    }
                }

                // if field exists, convert it to a more friendly format
                switch ($associationMapping['type']) {
                    case ClassMetadata::ONE_TO_ONE:
                        $transformedField = $this->transformOneToOneMapping($associationMapping, $entitiesByClass);
                        break;

                    case ClassMetadata::ONE_TO_MANY:
                        $transformedField = $this->transformOneToManyMapping($associationMapping, $entitiesByClass);
                        break;

                    case ClassMetadata::MANY_TO_ONE:
                        $transformedField = $this->transformManyToOneMapping($associationMapping, $entitiesByClass);
                        break;

                    case ClassMetadata::MANY_TO_MANY:
                        $transformedField = $this->transformManyToManyMapping($associationMapping, $entitiesByClass);
                        break;

                    default:
                        throw new \Exception('Unhandled relationship type: ' . $associationMapping['type']);
                }

                $transformedField['readOnly'] = $transformedConfiguration['fields'][$fieldIndex]['readOnly'];

                $transformedConfiguration['fields'][$fieldIndex] = $transformedField;
            }
        }

        return $entities;
    }

    public function reverseTransform($configWithRelationships)
    {
        throw new \DomainException("You shouldn't have to remove relationships from a ng-admin configuration.");
    }

    private function getFieldIndex(array $fields, $fieldName)
    {
        foreach($fields as $index => $field) {
            if ($field['name'] === $fieldName) {
                return $index;
            }
        }
    }

    private function transformOneToOneMapping($associationMapping, $entitiesByClass)
    {
        return [
            'name' => Inflector::tableize($associationMapping['fieldName']),
            'type' => 'reference',
            'referencedEntity' => [
                'name' => $entitiesByClass[$associationMapping['targetEntity']]['name'],
                'class' => $associationMapping['targetEntity']
            ],
            'referencedField' => $this->referencedFieldGuesser->guess($associationMapping['targetEntity'])
        ];
    }

    private function transformOneToManyMapping($associationMapping, $entitiesByClass)
    {
        return [
            'name' => Inflector::tableize($associationMapping['fieldName']),
            'type' => 'referenced_list',
            'referencedEntity' => [
                'name' => $entitiesByClass[$associationMapping['targetEntity']]['name'],
                'class' => $associationMapping['targetEntity']
            ],
            'referencedField' => $this->referencedFieldGuesser->guessOneToManyReferenceField($associationMapping)
        ];
    }

    private function transformManyToOneMapping($associationMapping, $entitiesByClass)
    {
        return [
            'name' => Inflector::tableize($associationMapping['fieldName']),
            'type' => 'reference',
            'referencedEntity' => [
                'name' => $entitiesByClass[$associationMapping['targetEntity']]['name'],
                'class' => $associationMapping['targetEntity']
            ],
            'referencedField' => $this->referencedFieldGuesser->guess($associationMapping['targetEntity'])
        ];
    }

    private function transformManyToManyMapping($associationMapping, $entitiesByClass)
    {
        return [
            'name' => Inflector::tableize($associationMapping['fieldName']),
            'type' => 'reference_many',
            'referencedEntity' => [
                'name' => $entitiesByClass[$associationMapping['targetEntity']]['name'],
                'class' => $associationMapping['targetEntity']
            ],
            'referencedField' => $this->referencedFieldGuesser->guess($associationMapping['targetEntity'])
        ];
    }
}
