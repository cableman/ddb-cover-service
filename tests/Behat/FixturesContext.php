<?php

/**
 * @file
 * Behat context for data fixtures.
 */

namespace App\Tests\Behat;

use App\DataFixtures\Elastic\ElasticService;
use App\DataFixtures\Faker\Search;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Index\Resetter;

/**
 * Class FixturesContext.
 */
class FixturesContext implements Context
{
    private $elasticService;

    private $configManager;
    private $indexManager;
    private $resetter;

    /**
     * FixturesContext constructor.
     *
     * @param ElasticService $elasticService
     * @param IndexManager $indexManager
     * @param Resetter $resetter
     * @param ConfigManager $configManager
     */
    public function __construct(ElasticService $elasticService, IndexManager $indexManager, Resetter $resetter, ConfigManager $configManager)
    {
        $this->elasticService = $elasticService;

        // @TODO: To be refactored when Fos\Elastica is removed.
        $this->configManager = $configManager;
        $this->indexManager = $indexManager;
        $this->resetter = $resetter;
    }

    /**
     * @Given the following search entries exits:
     *
     * @param TableNode $table
     *   Gherkin table argument containing columns identifier, type, url, autogenerated, image_format
     */
    public function theFollowingIdentifiersExits(TableNode $table): void
    {
        $searches = [];
        $searchId = 1;

        foreach ($table->getHash() as $row) {
            $search = new Search();

            $search->setId($searchId);
            $search->setIsIdentifier($row['identifier']);
            $search->setIsType(strtolower($row['type']));
            $search->setImageUrl($row['url']);
            $search->setImageFormat($row['image_format']);
            $search->setWidth($row['width']);
            $search->setHeight($row['height']);

            $searches[] = $search;

            ++$searchId;
        }

        $this->elasticService->index(...$searches);

        // Give elastic 1 second to build index.
        \sleep(1);
    }

    /**
     * Prepare Elastic indexes.
     *
     * @BeforeScenario @createFixtures
     */
    public function prepareIndexes(): void
    {
        $indexes = array_keys($this->indexManager->getAllIndexes());

        if (!$indexes) {
            $this->createIndexes();
        } else {
            $this->resetIndexes();
        }
    }

    /**
     * Reset Elastic indexes.
     */
    private function resetIndexes(): void
    {
        $indexes = array_keys($this->indexManager->getAllIndexes());

        foreach ($indexes as $index) {
            $this->resetter->resetIndex($index, false, true);
        }
    }

    /**
     * Create Elastic indexes.
     */
    private function createIndexes(): void
    {
        $indexes = array_keys($this->indexManager->getAllIndexes());

        foreach ($indexes as $indexName) {
            $indexConfig = $this->configManager->getIndexConfiguration($indexName);
            $index = $this->indexManager->getIndex($indexName);
            if ($indexConfig->isUseAlias()) {
                $this->aliasProcessor->setRootName($indexConfig, $index);
            }
            $mapping = $this->mappingBuilder->buildIndexMapping($indexConfig);
            $index->create($mapping, false);
        }
    }
}
