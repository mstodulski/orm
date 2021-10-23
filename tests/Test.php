<?php

use test\orm\helpers\EntityOne;
use test\orm\helpers\EntityTwo;
use test\orm\helpers\EntityWithoutConfiguration;
use test\orm\helpers\EntityZero;
use test\orm\helpers\Feature;
use test\orm\helpers\Price;
use test\orm\helpers\Product;
use test\orm\helpers\User;
use JetBrains\PhpStorm\NoReturn;
use mstodulski\database\Collection;
use mstodulski\database\EntityManager;
use mstodulski\database\HydrationMode;
use mstodulski\database\MySQLAdapter;
use mstodulski\database\ObjectMapper;
use mstodulski\database\OrmService;
use mstodulski\database\QueryCondition;
use mstodulski\database\QueryConditionOperator;
use mstodulski\database\QuerySorting;
use mstodulski\database\Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class Test extends TestCase
{
    private EntityManager $entityManager;
    private array $config;

    #[NoReturn] public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->config = getConfig();
        $mysqlAdapter = new $this->config['sqlAdapterClass']();
        $this->entityManager = EntityManager::create($mysqlAdapter, $this->config);
    }


//    public function testTimes()
//    {
//        $totalTimeStart = microtime(true);
//        $timeStart = microtime(true);
//        $product = $this->entityManager->find(Product::class, 7);
//        echo('czas pierwszego: ' . (microtime(true) - $timeStart) . ' sek.' . PHP_EOL);
//        for ($i=0; $i<=100; $i++) {
//            $timeStart = microtime(true);
//            $product = $this->entityManager->find(Product::class, 7);
//            echo('czas w pętli ' . $i . ': ' . (microtime(true) - $timeStart) . ' sek.' . PHP_EOL);
//        }
//        echo 'Całkowity czas: ' . (microtime(true) - $totalTimeStart) . ' sek.' . PHP_EOL;
//    }

    public function testGetExistingProductFromDatabase()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 7);

        $reflectionObjectFields = [];
        ObjectMapper::getClassProperties(Product::class, $reflectionObjectFields);

        $fkUsrCreatedByReflectionProperty = $reflectionObjectFields['FK_Usr_createdBy'];
        $fkUsrUpdatedByReflectionProperty = $reflectionObjectFields['FK_Usr_updatedBy'];
        $featuresReflectionProperty = $reflectionObjectFields['features'];
        $pricesReflectionProperty = $reflectionObjectFields['prices'];

        ObjectMapper::setFieldAccessible($fkUsrCreatedByReflectionProperty);
        ObjectMapper::setFieldAccessible($fkUsrUpdatedByReflectionProperty);
        ObjectMapper::setFieldAccessible($featuresReflectionProperty);
        ObjectMapper::setFieldAccessible($pricesReflectionProperty);

        $fkUsrCreatedByValue = $fkUsrCreatedByReflectionProperty->getValue($product);
        $fkUsrUpdatedByValue = $fkUsrUpdatedByReflectionProperty->getValue($product);
        $featuresValue = $featuresReflectionProperty->getValue($product);
        $pricesValue = $pricesReflectionProperty->getValue($product);

        $this->assertIsBool(get_class($fkUsrCreatedByValue) === 'mstodulski\orm\proxy\test\orm\helpers\User');
        $this->assertIsBool(get_class($fkUsrUpdatedByValue) === 'mstodulski\orm\proxy\test\orm\helpers\User');
        $this->assertIsBool(get_class($featuresValue) === 'mstodulski\database\LazyCollection ');
        $this->assertIsBool(get_class($pricesValue) === 'mstodulski\database\Collection');

        $this->assertEquals(false, $fkUsrCreatedByValue->___orm_initialized);
        $this->assertEquals(true, $fkUsrUpdatedByValue->___orm_initialized);

        $this->assertEquals('Produkt 7', $product->getName());
        $this->assertEquals(false, $product->isArchived());
        $this->assertIsFloat($product->getWeight());
        $this->assertEquals(6.63, $product->getWeight());

        $this->assertEquals('2021-08-01 13:01:23', $product->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('2021-08-02 15:01:23', $product->getUpdatedAt()->format('Y-m-d H:i:s'));

        $this->assertEquals('Kolor', $product->getFeatures()->getElementByIndex(0)->getName());
        $this->assertEquals('Czarny', $product->getFeatures()->getElementByIndex(0)->getValue());
        $this->assertEquals('Rozmiar', $product->getFeatures()->getElementByIndex(1)->getName());
        $this->assertEquals('S', $product->getFeatures()->getElementByIndex(1)->getValue());

        $this->assertEquals('Detaliczna', $product->getPrices()->getElementByIndex(0)->getName());
        $this->assertEquals('16.23', $product->getPrices()->getElementByIndex(0)->getValue());
        $this->assertEquals('Hurtowa', $product->getPrices()->getElementByIndex(1)->getName());
        $this->assertEquals('11.23', $product->getPrices()->getElementByIndex(1)->getValue());

        $this->assertEquals(true, $product->___orm_initialized);

        $this->assertEquals('user 3', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('user 1', $product->getFK_Usr_updatedBy()->getName());

        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());
    }

    public function testCreateNewProduct1()
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, 1);
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);
        /** @var EntityTwo $entityTwo */
        $entityTwo = $this->entityManager->find(EntityTwo::class, 2);

        $product = new Product();
        $product->setName('Produkt testowy, czapka z głowy');
        $product->setWeight(10.43);
        $product->setFK_Usr_createdBy($user);
        $product->setCreatedAt(new DateTime());
        $product->setEntityOne($entityOne);
        $product->setEntityTwo($entityTwo);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 11);

        $this->assertEquals('user 1', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(0, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(0, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(10.43, $product->getWeight());
        $this->assertEquals('Produkt testowy, czapka z głowy', $product->getName());
    }

    public function testCreateNewProduct2()
    {
        $user = new User();
        $user->setName('Użytkownik testowy xyz');
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);

        $product = new Product();
        $product->setName('Produkt testowy z nowo utworzonym userem');
        $product->setWeight(10.43);
        $product->setFK_Usr_createdBy($user);
        $product->setCreatedAt(new DateTime());
        $product->setEntityOne($entityOne);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 12);

        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertEquals('Użytkownik testowy xyz', $product->getFK_Usr_createdBy()->getName());
        $this->assertNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(0, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(0, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(10.43, $product->getWeight());
        $this->assertEquals('Produkt testowy z nowo utworzonym userem', $product->getName());
    }

    public function testCreateNewProduct3()
    {
        $userC = new User();
        $userC->setName('Użytkownik tworzący');
        $userU = new User();
        $userU->setName('Użytkownik aktualizujący');
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);
        $product = new Product();
        $product->setName('Produkt testowy z nowo utworzonym userem');
        $product->setWeight(10.43);
        $product->setFK_Usr_createdBy($userC);
        $product->setCreatedAt(new DateTime());
        $product->setFK_Usr_updatedBy($userU);
        $product->setUpdatedAt(new DateTime());
        $product->setEntityOne($entityOne);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 13);

        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertEquals('Użytkownik tworzący', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_updatedBy()));
        $this->assertEquals('Użytkownik aktualizujący', $product->getFK_Usr_updatedBy()->getName());
        $this->assertNotNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(0, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(0, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(10.43, $product->getWeight());
        $this->assertEquals('Produkt testowy z nowo utworzonym userem', $product->getName());
    }

    public function testCreateNewProduct4()
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, 1);
        $userU = new User();
        $userU->setName('Użytkownik aktualizujący 2');
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);
        $product = new Product();
        $product->setName('Produkt testowy, czapka z głowy 222');
        $product->setWeight(11.43);
        $product->setFK_Usr_createdBy($user);
        $product->setCreatedAt(new DateTime());
        $product->setFK_Usr_updatedBy($userU);
        $product->setUpdatedAt(new DateTime());
        $product->setEntityOne($entityOne);
        $product->getFK_Usr_createdBy()->setName('Apdacja użytkownika tworzącego');
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 14);

        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertEquals('Apdacja użytkownika tworzącego', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_updatedBy()));
        $this->assertEquals('Użytkownik aktualizujący 2', $product->getFK_Usr_updatedBy()->getName());
        $this->assertNotNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(0, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(0, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(11.43, $product->getWeight());
        $this->assertEquals('Produkt testowy, czapka z głowy 222', $product->getName());
    }

    public function testCreateNewProduct5()
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, 1);
        $userU = new User();
        $userU->setName('Użytkownik aktualizujący 333');
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);
        $product = new Product();
        $product->setName('Produkt testowy z nowo utworzonym userem i kolekcją features');
        $product->setWeight(12.43);
        $product->setFK_Usr_createdBy($user);
        $createdAt = new DateTime();
        $createdAt->modify('-3 hours');
        $product->setCreatedAt($createdAt);
        $product->setFK_Usr_updatedBy($userU);
        $updatedAt = new DateTime();
        $updatedAt->modify('-45 minutes');
        $product->setUpdatedAt($updatedAt);
        $product->setEntityOne($entityOne);

        $feature = new Feature();
        $feature->setCreatedAt(new DateTime());
        $feature->setFK_Usr_createdBy($user);
        $feature->setName('Kolor');
        $feature->setValue('TESTOWY KOLOR');
        $product->getFeatures()->add($feature);

        $feature = new Feature();
        $feature->setCreatedAt(new DateTime());
        $feature->setFK_Usr_createdBy($user);
        $feature->setName('Rozmiar');
        $feature->setValue('TESTOWY ROZMIAR');
        $product->getFeatures()->add($feature);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 15);

        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertEquals('Apdacja użytkownika tworzącego', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_updatedBy()));
        $this->assertEquals('Użytkownik aktualizujący 333', $product->getFK_Usr_updatedBy()->getName());
        $this->assertNotNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(0, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(12.43, $product->getWeight());
        $this->assertEquals('Produkt testowy z nowo utworzonym userem i kolekcją features', $product->getName());

        $this->assertEquals('Kolor', $product->getFeatures()->getElementByIndex(0)->getName());
        $this->assertEquals('TESTOWY KOLOR', $product->getFeatures()->getElementByIndex(0)->getValue());
        $this->assertEquals('Rozmiar', $product->getFeatures()->getElementByIndex(1)->getName());
        $this->assertEquals('TESTOWY ROZMIAR', $product->getFeatures()->getElementByIndex(1)->getValue());

    }

    public function testCreateNewProduct6()
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, 1);
        $userU = new User();
        $userU->setName('Użytkownik aktualizujący 4444');
        /** @var EntityOne $entityOne */
        $entityOne = $this->entityManager->find(EntityOne::class, 1);
        $product = new Product();
        $product->setName('Produkt testowy z nowo utworzonym userem i kolekcjami');
        $product->setWeight(12.43);
        $product->setFK_Usr_createdBy($user);
        $createdAt = new DateTime();
        $createdAt->modify('-3 hours');
        $product->setCreatedAt($createdAt);
        $product->setFK_Usr_updatedBy($userU);
        $product->setEntityOne($entityOne);
        $updatedAt = new DateTime();
        $updatedAt->modify('-45 minutes');
        $product->setUpdatedAt($updatedAt);

        $feature = new Feature();
        $feature->setCreatedAt(new DateTime());
        $feature->setFK_Usr_createdBy($user);
        $feature->setName('Kolor');
        $feature->setValue('TESTOWY KOLOR');
        $product->getFeatures()->add($feature);

        $feature = new Feature();
        $feature->setCreatedAt(new DateTime());
        $feature->setFK_Usr_createdBy($user);
        $feature->setName('Rozmiar');
        $feature->setValue('TESTOWY ROZMIAR');
        $product->getFeatures()->add($feature);

        $price = new Price();
        $price->setCreatedAt(new DateTime());
        $price->setFK_Usr_createdBy($user);
        $price->setCreatedAt(new DateTime());
        $price->setName('Dla wybrańców');
        $price->setValue(456.23);
        $product->getPrices()->add($price);

        $price = new Price();
        $price->setCreatedAt(new DateTime());
        $price->setFK_Usr_createdBy($user);
        $price->setCreatedAt(new DateTime());
        $price->setName('Dla leszczyków');
        $price->setValue(856.23);
        $product->getPrices()->add($price);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 16);

        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_createdBy()));
        $this->assertEquals('Apdacja użytkownika tworzącego', $product->getFK_Usr_createdBy()->getName());
        $this->assertEquals('mstodulski\orm\proxy\test\orm\helpers\User', get_class($product->getFK_Usr_updatedBy()));
        $this->assertEquals('Użytkownik aktualizujący 4444', $product->getFK_Usr_updatedBy()->getName());
        $this->assertNotNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());
        $this->assertFalse($product->isArchived());
        $this->assertEquals(12.43, $product->getWeight());
        $this->assertEquals('Produkt testowy z nowo utworzonym userem i kolekcjami', $product->getName());

        $this->assertEquals('Kolor', $product->getFeatures()->getElementByIndex(0)->getName());
        $this->assertEquals('TESTOWY KOLOR', $product->getFeatures()->getElementByIndex(0)->getValue());
        $this->assertEquals('Rozmiar', $product->getFeatures()->getElementByIndex(1)->getName());
        $this->assertEquals('TESTOWY ROZMIAR', $product->getFeatures()->getElementByIndex(1)->getValue());

        $this->assertEquals('Dla wybrańców', $product->getPrices()->getElementByIndex(0)->getName());
        $this->assertEquals(456.23, $product->getPrices()->getElementByIndex(0)->getValue());
        $this->assertEquals('Dla leszczyków', $product->getPrices()->getElementByIndex(1)->getName());
        $this->assertEquals(856.23, $product->getPrices()->getElementByIndex(1)->getValue());
    }

    public function testCreateNewProduct7()
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, 9);
        /** @var Product $pro */
        $product = $this->entityManager->find(Product::class, 1);
        $product->setArchived(true);

        $feature = new Feature();
        $feature->setCreatedAt(new DateTime());
        $feature->setFK_Usr_createdBy($user);
        $feature->setName('Magazyn');
        $feature->setValue('MAGAZYN 1');
        $product->getFeatures()->add($feature);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $product = $this->entityManager->find(Product::class, 1);

        $this->assertNotNull($product->getFK_Usr_createdBy());
        $this->assertNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(3, $product->getFeatures()->getRecordsCount());

        $this->assertEquals('Magazyn', $product->getFeatures()->getElementByIndex(2)->getName());
        $this->assertEquals('MAGAZYN 1', $product->getFeatures()->getElementByIndex(2)->getValue());
    }

    public function testCreateNewProduct8()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 1);
        $product->setArchived(true);
        $index = $product->getFeatures()->getIndexByFieldValue('value', 'XL');
        $product->getFeatures()->remove($index);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 1);

        $this->assertTrue($product->isArchived());
        $this->assertNotNull($product->getFK_Usr_createdBy());
        $this->assertNull($product->getFK_Usr_updatedBy());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());

        $this->assertEquals('Magazyn', $product->getFeatures()->getElementByIndex(1)->getName());
        $this->assertEquals('MAGAZYN 1', $product->getFeatures()->getElementByIndex(1)->getValue());
    }

    public function testCreateNewProduct9()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 1);
        /** @var Feature $feature */
        foreach ($product->getFeatures() as $feature) {
            $feature->setName('Po edycji');
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 1);

        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());

        $this->assertEquals('Po edycji', $product->getFeatures()->getElementByIndex(0)->getName());
        $this->assertEquals('Po edycji', $product->getFeatures()->getElementByIndex(1)->getName());

    }

    public function testProduct1()
    {
        $products = $this->entityManager->findBy(Product::class, ['sortOrder' => 1, 'creatorBrowser' => 'Fajerfoks']);
        /** @var Product $product */
        $product = $products[0];

        $this->assertEquals('Produkt 1', $product->getName());
        $this->assertEquals(1.23, $product->getWeight());

        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals('Po edycji', $product->getFeatures()->getElementByIndex(0)->getName());
        $this->assertEquals('Po edycji', $product->getFeatures()->getElementByIndex(1)->getName());
    }

    public function testProduct2()
    {
        $count = $this->entityManager->count(Product::class, ['sortOrder' => 1, 'creatorBrowser' => 'Fajerfoks']);
        $this->assertEquals(1, $count);
    }

    public function testProduct3()
    {
        $product = $this->entityManager->find(Product::class, 1);
        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $product = $this->entityManager->find(Product::class, 1);

        $this->assertNull($product);
    }

    public function testProduct4()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $product = $repository->find(2);

        $this->assertEquals('Produkt 2', $product->getName());
        $this->assertEquals(2.33, $product->getWeight());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());
    }

    public function testProduct6()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $count = $repository->count(['creatorBrowser' => 'Fajerfoks']);

        $this->assertEquals(9, $count);
    }

    public function testProduct7()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $products = $repository->findAll();

        /** @var Product $product */
        $product = $products[0];

        $this->assertEquals('Produkt 2', $product->getName());
        $this->assertEquals(2.33, $product->getWeight());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());
    }

    public function testProduct8()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $products = $repository->findAll(HydrationMode::HYDRATION_ARRAY);

        /** @var Product $product */
        $product = $products[0];

        $this->assertEquals('Produkt 2', $product['name']);
        $this->assertEquals(2.33, $product['weight']);
        $this->assertEquals(2, $product['id']);
        $this->assertEquals(2, $product['sortOrder']);
        $this->assertEquals(0, $product['archived']);
    }

    public function testProduct9()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $products = $repository->findBy(['sortOrder' => 2, 'creatorBrowser' => 'Fajerfoks']);

        /** @var Product $product */
        $product = $products[0];

        $this->assertEquals('Produkt 2', $product->getName());
        $this->assertEquals(2.33, $product->getWeight());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());
    }

    public function testProduct10()
    {
        $repository = $this->entityManager->createRepository(Product::class);
        $products = $repository->findBy(['sortOrder' => 2, 'creatorBrowser' => 'Fajerfoks'], HydrationMode::HYDRATION_ARRAY);

        /** @var Product $product */
        $product = $products[0];

        $this->assertEquals('Produkt 2', $product['name']);
        $this->assertEquals(2.33, $product['weight']);
        $this->assertEquals(2, $product['id']);
        $this->assertEquals(2, $product['sortOrder']);
        $this->assertEquals(0, $product['archived']);
    }

    public function testCollection1()
    {
        $collection = new Collection(Product::class);
        $this->assertEquals(Product::class, $collection->getCollectionClass());

        $product = new Product();
        $collection = new Collection($product);
        $this->assertEquals(Product::class, $collection->getCollectionClass());

        $this->assertEquals(0, $collection->key());
    }

    public function testCollection2()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 2);
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());

        $product->getFeatures()->clear();
        $this->assertEquals(0, $product->getFeatures()->getRecordsCount());
    }

    public function testCollection3()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 2);

        $this->expectException(Exception::class);
        $collection = new Collection(Feature::class);
        $collection->add($product);
    }

    public function testCollection4()
    {
        $collection = new Collection(Product::class);
        /** @var Product $product1 */
        $product1 = $this->entityManager->find(Product::class, 2);
        $collection->add($product1);

        /** @var Product $product2 */
        $product2 = $this->entityManager->find(Product::class, 3);
        $collection->replaceCollectionElementAtIndex($product2, 0);

        /** @var Product $product */
        $product = $collection->getElementByIndex(0);

        $this->assertEquals('Produkt 3', $product->getName());
        $this->assertEquals(3.43, $product->getWeight());
        $this->assertEquals(2, $product->getFeatures()->getRecordsCount());
        $this->assertEquals(2, $product->getPrices()->getRecordsCount());

        $this->assertFalse($product->getPrices()->isEmpty());

        $product = new Product();
        $this->assertTrue($product->getPrices()->isEmpty());


    }

    public function testCollection5()
    {
        $collection = new Collection(Product::class);
        /** @var Product $product1 */
        $product1 = $this->entityManager->find(Product::class, 2);
        $collection->add($product1);

        /** @var Feature $feature */
        $feature = $this->entityManager->find(Feature::class, 3);

        $this->expectException(Exception::class);

        $collection->replaceCollectionElementAtIndex($feature, 0);
    }

    public function testCollection6()
    {
        /** @var Product $product */
        $product = $this->entityManager->find(Product::class, 2);
        $price = $product->getPrices()->findOneByFieldValue('value', 1234312.12);
        $priceIndex = $product->getPrices()->getIndexByFieldValue('value', 1234312.12);

        $this->assertNull($price);
        $this->assertNull($priceIndex);
    }

    public function testCollection7()
    {
        $collection = new Collection(Product::class);
        /** @var Product $product1 */
        $product1 = $this->entityManager->find(Product::class, 2);
        $collection->add($product1);

        /** @var Product $product2 */
        $product2 = $this->entityManager->find(Product::class, 3);
        $collection->replaceCollectionElementAtIndex($product2, 1);

        $this->assertEquals(2, $collection->getRecordsCount());
    }

    public function testEntityManager1()
    {
        $product = new Product();

        $this->expectException(PDOException::class);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function testEntityManager2()
    {
        $this->expectException(Exception::class);
        $this->entityManager->find(EntityWithoutConfiguration::class, 1);
    }

    public function testEntityManager3()
    {
        $mysqlAdapter = new MySQLAdapter();
        $this->config['mode'] = 'dev';
        $entityManager = EntityManager::create($mysqlAdapter, $this->config);

        $product = $entityManager->find(Product::class, 2);

        $this->assertEquals('Produkt 2', $product->getName());
    }

    public function testEntityManager4()
    {
        $mysqlAdapter = new MySQLAdapter();
        $this->config['mode'] = 'strangeMode';
        $this->expectException(Exception::class);
        EntityManager::create($mysqlAdapter, $this->config);
    }

    public function testEntityManager5()
    {
        /** @var Product $product1 */
        $this->expectException(Exception::class);
        $this->entityManager->find(Product::class, 2, 123);

        $this->expectException(Exception::class);
        $this->entityManager->findBy(Product::class, ['id' => 2], 123);
    }

    public function testDbConnection1()
    {
        $dateTime = new DateTime();
        $dateTime->modify('+3 hour');

        $mysqlAdapter = new MySQLAdapter();
        $this->config['mode'] = 'prod';
        $this->entityManager = EntityManager::create($mysqlAdapter, $this->config);

        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addWhere(new QueryCondition('p.createdAt > :createdAt', $dateTime))
            ->addWhere(new QueryCondition('p.id = :id', 2))
        ;

        /** @var Product $product */
        $products = $queryBuilder->getSingleResult();

        $this->assertEmpty($products);
    }

    public function testDbConnection2()
    {
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addWhere(new QueryCondition('p.test > :test', 'test'));

        $this->expectException(PDOException::class);
        $queryBuilder->getTableResult();
    }

    public function testDbConnection3()
    {
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addWhere(new QueryCondition('p.test > :test', 'test'));

        $this->expectException(PDOException::class);
        $queryBuilder->getSingleResult();
    }

    public function testDbConnection3a()
    {
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addWhere(new QueryCondition('p.test > :test', 'test'));

        $this->expectException(PDOException::class);
        $queryBuilder->getValue();
    }

    public function testDbConnection4()
    {
        $user = $this->entityManager->find(User::class, 1);
        $sorting = new QuerySorting('p.id', QuerySorting::DIRECTION_ASC);

        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addWhere(new QueryCondition('p.FK_Usr_createdBy = :FK_Usr_createdBy', $user))
            ->setSorting($sorting)
        ;

        $product = $queryBuilder->getSingleResult();

        $this->assertEquals(5, $product->getId());
    }

    public function testDbConnection5()
    {
        $this->assertFalse($this->entityManager->getDbConnection()->checkIfTransactionStarted());
        $this->assertInstanceOf(PDO::class, $this->entityManager->getDbConnection()->getConnection());
    }

    public function testLazyEntity1()
    {
        $product = new Product();
        $this->assertNull($product->getFK_Usr_updatedBy());
    }

    public function testQueryBuilder1()
    {
        $priceRepository = $this->entityManager->createRepository(Price::class);
        $qb = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $joinCondition = new QueryCondition();
        $conditionPriceJoin = new QueryCondition('p.id = pr.FK_Pro_product');
        $conditionPriceName = new QueryCondition('pr.name = :priceName', 'Detaliczna');
        $joinCondition->addCondition($conditionPriceJoin);
        $joinCondition->addCondition($conditionPriceName, QueryCondition::AND_OPERATOR);

        $qb
            ->addField('p.id')
            ->addField('p.name')
            ->addField('p.FK_Usr_createdBy')
            ->addField('p.weight as productWeight')
            ->addField('pr.value as priceValue')
            ->addWhere(new QueryCondition('p.name = :name', 'Produkt 7'))
            ->addWhere(new QueryCondition('p.id = :id', 7))
            ->addGroupBy('p.id')
            ->addJoin($priceRepository, 'pr', $joinCondition)
            ->addHaving(new QueryCondition('productWeight > :productWeight', 6.6))
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_DESC))
            ->setOffset(0)
            ->setLimit(100)
        ;

        $products = $qb->getTableResult(HydrationMode::HYDRATION_ARRAY);
        $product = $products[0];

        $this->assertEquals(7, $product['id']);
        $this->assertEquals('Produkt 7', $product['name']);
        $this->assertEquals(3, $product['FK_Usr_createdBy']);
        $this->assertEquals(6.63, $product['productWeight']);
        $this->assertEquals(16.23, $product['priceValue']);
    }

    public function testQueryBuilder2()
    {
        $qb = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $querySorting = new QuerySorting();
        $querySorting->addField('p.id', QuerySorting::DIRECTION_ORDERED, [7,2,4], QuerySorting::DIRECTION_ASC);

        $whereCondition = new QueryCondition();
        $where1 = new QueryCondition('p.id = :id1', 7);
        $where2 = new QueryCondition('p.id = :id2', 2);
        $where3 = new QueryCondition('p.id = :id3', 4);
        $whereCondition->addCondition($where1);
        $whereCondition->addCondition($where2, QueryCondition::OR_OPERATOR);
        $whereCondition->addCondition($where3, QueryCondition::OR_OPERATOR);

        $qb
            ->setSorting($querySorting)
            ->addWhere($whereCondition)
        ;

        $products = $qb->getTableResult(HydrationMode::HYDRATION_ARRAY);

        $product1 = $products[0];
        $product3 = $products[2];

        $this->assertEquals(7, $product1['id']);
        $this->assertEquals(4, $product3['id']);

        $querySorting->clear();
        $this->assertEmpty($querySorting->getFields());
    }

    public function testQueryBuilder3()
    {
        $qb = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $querySorting = new QuerySorting('p.id', QuerySorting::DIRECTION_RANDOM);
        $qb->setSorting($querySorting);
        $product1 = $qb->getSingleResult(HydrationMode::HYDRATION_ARRAY);
        $product2 = $qb->getSingleResult(HydrationMode::HYDRATION_ARRAY);
        $product3 = $qb->getSingleResult(HydrationMode::HYDRATION_ARRAY);

        $this->assertTrue(
            ($product1['id'] != $product2['id']) ||
            ($product1['id'] != $product3['id']) ||
            ($product2['id'] != $product3['id'])
        );
    }

    public function testQueryBuilder4()
    {
        $priceRepository = $this->entityManager->createRepository(Price::class);

        $qb = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $querySorting = new QuerySorting();
        $querySorting->addField('p.id', QuerySorting::DIRECTION_ORDERED, [7,2,4], QuerySorting::DIRECTION_ASC);

        $whereCondition = new QueryCondition();
        $where1 = new QueryCondition('p.id = :id1', 7);
        $where2 = new QueryCondition('p.id = :id2', 2);
        $where3 = new QueryCondition('p.id = :id3', 4);
        $whereCondition->addCondition($where1);
        $whereCondition->addCondition($where2, QueryCondition::OR_OPERATOR);
        $whereCondition->addCondition($where3, QueryCondition::OR_OPERATOR);

        $joinCondition = new QueryCondition();
        $conditionPriceJoin = new QueryCondition('p.id = pr.FK_Pro_product');
        $conditionPriceName = new QueryCondition('pr.name = :priceName', 'Detaliczna');
        $joinCondition->addCondition($conditionPriceJoin);
        $joinCondition->addCondition($conditionPriceName, QueryCondition::AND_OPERATOR);

        $qb
            ->setSorting($querySorting)
            ->addWhere($whereCondition)
            ->addJoin($priceRepository, 'pr', $joinCondition)
        ;

        $products = $qb->getTableResult();

        /** @var Product $product1 */
        $product1 = $products[0];
        /** @var Product $product2 */
        $product3 = $products[2];

        $this->assertEquals(7, $product1->getId());
        $this->assertEquals(4, $product3->getId());

        $count = $qb->getCount();

        $this->assertEquals(3, $count);
    }

    public function testQueryBuilder5()
    {
        $productRepository = $this->entityManager->createRepository(Product::class);
        $qb = $productRepository->createQueryBuilder('p');

        $condition = new QueryCondition();
        $condition->addCondition('p.id = :id');
        $condition->addParameter('id', 7);

        $qb->addWhere($condition);

        /** @var Product $product */
        $product = $qb->getSingleResult();

        $this->assertEquals(7, $product->getId());

        $this->assertInstanceOf(EntityManager::class, $qb->getEntityManager());
        $qb->setEntityManager($this->entityManager);
        $qb->setTableAlias('tableAlias');
        $this->assertEquals('tableAlias', $qb->getTableAlias());

        $qb->clear();

        $this->assertEmpty($qb->getFields());
        $this->assertEmpty($qb->getJoins());
    }

    public function testRepository1()
    {
        $this->expectException(Exception::class);
        new Repository('UnknownClass', $this->entityManager);
    }

    public function testRepository2()
    {
        $tableName = Repository::createTableNameFromEntityClass('EntityWithSeveralPropertiesInName');
        $this->assertEquals('entity_with_several_properties_in_name', $tableName);

        $tableName = Repository::createTableNameFromEntityClass('Entity');
        $this->assertEquals('entity', $tableName);

        $tableName = Repository::createTableNameFromEntityClass('ENTITY');
        $this->assertEquals('entity', $tableName);
    }

    public function testRepository3()
    {
        $repository = new Repository(Product::class, $this->entityManager, 'product');
        /** @var Product $product */
        $product = $repository->find(2);

        $this->assertEquals(2, $product->getId());
    }

    public function testQueryCondition1()
    {
        $queryCondition = new QueryCondition();
        $qc1 = new QueryCondition(QueryConditionOperator::equals('p.id', 'id', 2));
        $qc2 = new QueryCondition(QueryConditionOperator::equals('p.id', 'id', 3));
        $queryCondition->addCondition($qc1);
        $this->expectException(Exception::class);
        $queryCondition->addCondition($qc2);
    }

    public function testQueryCondition2()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::differs('p.name', 'test', QueryConditionOperator::VALUE_KIND_VALUE));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->getSingleResult()
        ;

        $this->assertTrue($product->getName() != 'test');
    }

    public function testQueryCondition3()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::gt('p.id', 1));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->getSingleResult()
        ;

        $this->assertGreaterThan(1, $product->getId());
    }

    public function testQueryCondition4()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::gte('p.id', 1));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->getSingleResult()
        ;

        $this->assertGreaterThanOrEqual(2, $product->getId());
    }

    public function testQueryCondition5()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::lt('p.id', 3));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->getSingleResult()
        ;

        $this->assertLessThan(3, $product->getId());
    }

    public function testQueryCondition6()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::lte('p.id', 2));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(2, $product->getId());
    }

    public function testQueryCondition7()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::isNull('p.entityTwo'));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(7, $product->getId());
    }

    public function testQueryCondition8()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::isNotNull('p.entityTwo'));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(2, $product->getId());
    }

    public function testQueryCondition9()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::in('p.id', [3,4,5]));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(3, $product->getId());
    }

    public function testQueryCondition10()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::notIn('p.id', [2,3,4,5]));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(6, $product->getId());
    }

    public function testQueryCondition11()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::contains('p.creatorBrowser', 'Fajerfoks', QueryConditionOperator::VALUE_KIND_VALUE));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        /** @var Product $product */
        $product = $queryBuilder
            ->addWhere($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
            ->getSingleResult()
        ;

        $this->assertLessThanOrEqual(3, $product->getId());
    }

    public function testHaving1()
    {
        $queryCondition = new QueryCondition(QueryConditionOperator::contains('browser', 'Fajerfoks', QueryConditionOperator::VALUE_KIND_VALUE));
        $queryBuilder = $this->entityManager->createQueryBuilder(Product::class, 'p');

        $queryBuilder
            ->addField('p.id')
            ->addField('p.name')
            ->addField('p.creatorBrowser as browser')
            ->addHaving($queryCondition)
            ->setSorting(new QuerySorting('p.id', QuerySorting::DIRECTION_ASC))
        ;

        $product = $queryBuilder->getSingleResult(HydrationMode::HYDRATION_ARRAY);

        $this->assertEquals(2, $product['id']);
    }

    public function testEntityManager6()
    {
        $product = $this->entityManager->find(Product::class, 2, HydrationMode::HYDRATION_ARRAY);
        $this->assertEquals(2, $product['id']);
    }

    public function testMigration1()
    {
        $entityZeroOrmFile['entity'] = 'test\orm\helpers\EntityZero';
        $entityZeroOrmFile['repository'] = 'mstodulski\database\Repository';
        $entityZeroOrmFile['fields']['id']['type'] = 'int';
        $entityZeroOrmFile['fields']['id']['id'] = true;
        $entityZeroOrmFile['fields']['id']['name'] = 'auto_increment';
        $entityZeroOrmFile['fields']['name']['type'] = 'varchar';

        $content = Yaml::dump($entityZeroOrmFile, 8);
        file_put_contents($this->config['entityConfigurationDir'] . 'EntityZero.orm.yml', $content);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "generate";
        $arguments[] = "migration";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "migrate";

        OrmService::route($arguments);

        $tables = $this->entityManager->getTablesFromDb();
        $newTable = null;

        foreach ($tables as $table) {
            if ($table['Tables_in_orm'] == 'entity_zero') {
                $newTable = $table['Tables_in_orm'];
            }
        }

        $this->assertEquals('entity_zero', $newTable);
    }

    public function testMigration2()
    {
        $entityZeroFixture['fixture']['fixtureOrder'] = '3';
        $entityZeroFixture['fixture']['class'] = 'test\orm\helpers\EntityZero';
        $entityZeroFixture['fixture']['factoryClass'] = 'test\orm\helpers\EntityZeroMigrationFactory';
        $record['id'] = 1;
        $record['name'] = 'encja 0 1';
        $entityZeroFixture['fixture']['records'][] = $record;
        $record['id'] = 2;
        $record['name'] = 'encja 0 2';
        $entityZeroFixture['fixture']['records'][] = $record;

        $content = Yaml::dump($entityZeroFixture, 8);
        file_put_contents($this->config['fixtureDir'] . 'EntityZero.yml', $content);

        if (is_dir(getcwd() . '/' . $this->config['migrationDir'])) {
            deleteDir(getcwd() . '/' . $this->config['migrationDir']);
        }

        $query = /** @lang */"SELECT concat('DROP TABLE IF EXISTS `', table_name, '`;')
                    FROM information_schema.tables
                    WHERE table_schema = '" . $this->entityManager->getDsnValue('dbname') . "';";

        $queries = $this->entityManager->getDbConnection()->getTable($query);

        $this->entityManager->turnOffCheckForeignKeys();
        foreach ($queries as $query) {
            $query = reset($query);
            $this->entityManager->getDbConnection()->executeQuery($query);
        }
        $this->entityManager->turnOnCheckForeignKeys();

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "generate";
        $arguments[] = "migration";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "migrate";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "import";
        $arguments[] = "fixtures";

        OrmService::route($arguments);

        $repository = $this->entityManager->createRepository(EntityZero::class);
        /** @var EntityZero $entityZero */
        $entityZero = $repository->find(2);

        $this->assertEquals('encja 0 2', $entityZero->getName());
    }

    public function testMigration3()
    {
        $entityZeroOrmFile = Yaml::parseFile($this->config['entityConfigurationDir'] . 'EntityZero.orm.yml');
        $entityZeroOrmFile['fields']['entityTwo']['type'] = 'entity';
        $entityZeroOrmFile['fields']['entityTwo']['entityClass'] = 'test\orm\helpers\EntityTwo';
        $entityZeroOrmFile['fields']['entityTwo']['nullable'] = true;
        $entityZeroOrmFile['fields']['entityTwo']['lazy'] = false;

        $content = Yaml::dump($entityZeroOrmFile, 8);
        file_put_contents($this->config['entityConfigurationDir'] . 'EntityZero.orm.yml', $content);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "generate";
        $arguments[] = "migration";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "migrate";

        OrmService::route($arguments);

        apcu_clear_cache();

        $repository = $this->entityManager->createRepository(EntityZero::class);
        /** @var EntityZero $entityZero */
        $entityZero = $repository->find(2);

        $repositoryEntityTwo = $this->entityManager->createRepository(EntityTwo::class);
        /** @var EntityTwo $entityTwo */
        $entityTwo = $repositoryEntityTwo->find(2);

        $entityZero->setEntityTwo($entityTwo);
        $this->entityManager->persist($entityZero);
        $this->entityManager->flush();

        /** @var EntityZero $entityZero */
        $entityZero = $repository->find(2);

        $this->assertEquals('encja 2 2', $entityZero->getEntityTwo()->getName());
    }

    public function testMigration5()
    {
        $entityZeroOrmFile = Yaml::parseFile($this->config['entityConfigurationDir'] . 'EntityZero.orm.yml');
        unset($entityZeroOrmFile['fields']['entityTwo']);
        $content = Yaml::dump($entityZeroOrmFile, 8);
        file_put_contents($this->config['entityConfigurationDir'] . 'EntityZero.orm.yml', $content);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "generate";
        $arguments[] = "migration";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "migrate";

        OrmService::route($arguments);

        $repository = $this->entityManager->createRepository(EntityZero::class);
        /** @var EntityZero $entityZero */
        $entityZero = $repository->find(2);


        $this->assertNull($entityZero->getEntityTwo());
    }

    public function testMigration6()
    {
        if (file_exists('tests/config/EntityZero.orm.yml')) {
            unlink('tests/config/EntityZero.orm.yml');
        }

        if (file_exists('tests/fixtures/entityZero.yml')) {
            unlink('tests/fixtures/entityZero.yml');
        }

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "generate";
        $arguments[] = "migration";

        OrmService::route($arguments);

        $arguments = [];
        $arguments[] = "bin/morm";
        $arguments[] = "-dsn";
        $arguments[] = $this->config['dsn'];
        $arguments[] = "-u";
        $arguments[] = $this->config['user'];
        if ($this->config['password'] != '') {
            $arguments[] = "-p";
            $arguments[] = $this->config['password'];
        }
        $arguments[] = "-cd";
        $arguments[] = $this->config['entityConfigurationDir'];
        $arguments[] = "-md";
        $arguments[] = $this->config['migrationDir'];
        $arguments[] = "-fd";
        $arguments[] = $this->config['fixtureDir'];
        $arguments[] = "-ac";
        $arguments[] = $this->config['sqlAdapterClass'];
        $arguments[] = "migrate";

        OrmService::route($arguments);

        $tables = $this->entityManager->getTablesFromDb();

        $entityZeroTable = null;
        foreach ($tables as $table) {
            if ($table['Tables_in_orm'] == 'entity_zero') {
                $entityZeroTable = $table['Tables_in_orm'];
            }
        }

        $this->assertNull($entityZeroTable);
    }
}
