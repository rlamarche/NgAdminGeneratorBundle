<?php

namespace marmelab\NgAdminGeneratorBundle\Generator;

use Doctrine\ORM\EntityManagerInterface;
use marmelab\NgAdminGeneratorBundle\Transformer\TransformerInterface;

class ConfigurationGenerator
{
    private $em;
    private $twig;

    /** @var TransformerInterface[] */
    private $transformers = [];

    public function __construct(array $transformers = [], EntityManagerInterface $em, \Twig_Environment $twig)
    {
        $this->transformers = $transformers;
        $this->em = $em;
        $this->twig = $twig;
    }

    public function generateConfiguration(array $objectDefinitions)
    {
        if (empty($objectDefinitions)) {
            throw new \RuntimeException("No entity available for generation.");
        }

        $transformedData = [];

        foreach ($this->transformers as $transformer) {
            $inputData = count($transformedData) ? $transformedData: $objectDefinitions;

            $transformedData = $transformer->transform($inputData);
        }

        $dataWithKeys = [];
        foreach ($transformedData as $data) {
            $dataWithKeys[$data['name']] = $data;
        }

        return $this->twig->render('marmelabNgAdminGeneratorBundle:Configuration:config.js.twig', [
            'entities' => $dataWithKeys
        ]);
    }
}
