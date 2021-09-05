<?php
/**
 * This file is part of the EasyCore package.
 *
 * (c) Marcin Stodulski <marcin.stodulski@devsprint.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace mstodulski\database;

use Exception;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;

class ObjectFactory {

    public static function filterFieldsByType(array $array, array $types = ['entity', 'collection'], $lazy = true) : array
    {
        $fields = array_filter($array, function($fieldProperties) use ($types) {
            return in_array($fieldProperties['type'], $types);
        }, ARRAY_FILTER_USE_BOTH);

        if (!$lazy) {
            foreach ($fields as $fieldName => $fieldData) {
                if (isset($fieldData['lazy']) && !$fieldData['lazy']) {
                    unset($fields[$fieldName]);
                }
            }
        }

        return $fields;
    }

    private static function addOrmInitializedProperty(array $class)
    {
        $propertyProperty = new PropertyProperty('___orm_initialized');
        $propertyProperty->default = new ConstFetch(new Name('false'));
        $property = new Property(1, [$propertyProperty]);
        $class[0]->stmts[] = $property;
    }

    private static function getNewNamespace($objectClass) : array
    {
        $newClassNameArray = explode('\\', $objectClass);
        array_pop($newClassNameArray);

        if (!str_starts_with($objectClass, 'mstodulski\orm\proxy')) {
            array_unshift($newClassNameArray, 'mstodulski', 'orm', 'proxy');
        }

        return $newClassNameArray;
    }

    private static function modifyNamespace($objectClass, array $namespace)
    {
        $namespace[0]->name->parts = self::getNewNamespace($objectClass);
    }

    private static function addExtendingClassUse($objectClass, array $namespace)
    {
        $objectClassArray = explode('\\', $objectClass);
        $useUse = new UseUse(new Name($objectClassArray));
        $useUse->alias = new Identifier(implode(explode('\\', $objectClass)));
        $use = new Use_([$useUse]);
        array_unshift($namespace[0]->stmts, $use);
    }

    private static function addUses(array $namespace, array $oldUses) : array
    {
        $addedClasses = [];

        foreach ($oldUses as $className => $useObject) {
            if (($className !== LazyCollection::class) && ($className !== LazyEntity::class)) {
                $addedClasses[$className] = $className;
                $useUse = new UseUse(new Name($className));
                $use = new Use_([$useUse]);
                array_unshift($namespace[0]->stmts, $use);
            }
        }

        $useUse = new UseUse(new Name(LazyCollection::class));
        $addedClasses[LazyCollection::class] = LazyCollection::class;
        $use = new Use_([$useUse]);
        array_unshift($namespace[0]->stmts, $use);

        $useUse = new UseUse(new Name(LazyEntity::class));
        $addedClasses[LazyEntity::class] = LazyEntity::class;
        $use = new Use_([$useUse]);
        array_unshift($namespace[0]->stmts, $use);

        $useUse = new UseUse(new Name(ObjectMapper::class));
        $addedClasses[ObjectMapper::class] = ObjectMapper::class;
        $use = new Use_([$useUse]);
        array_unshift($namespace[0]->stmts, $use);

        $useUse = new UseUse(new Name(ReflectionClass::class));
        $addedClasses[ReflectionClass::class] = ReflectionClass::class;
        $use = new Use_([$useUse]);
        array_unshift($namespace[0]->stmts, $use);

        return $addedClasses;
    }

    private static function addReturnTypeUses(array $namespace, array $classes, array $addedClasses = [])
    {
        foreach ($classes as $className) {
            if (!isset($addedClasses[$className])) {
                $useUse = new UseUse(new Name($className));
                $use = new Use_([$useUse]);
                array_unshift($namespace[0]->stmts, $use);
            }
        }
    }

    private static function modifyClassExtends($objectClass, array $class)
    {
        if ($class[0]->extends === null) {
            $class[0]->extends = new Name(implode(explode('\\', $objectClass)));
        } else {
            $class[0]->extends->parts = [implode(explode('\\', $objectClass))];
        }
    }

    private static function createGetters($fieldsToOverwrite, array $class, array $oldMethods, string $currentNamespace): array
    {
        $returnTypeObjectClasses = [];

        foreach ($fieldsToOverwrite as $fieldToOverwrite => $fieldProperties)
        {
            $getterName = 'get' . ucfirst($fieldToOverwrite);
            $oldMethod = null;

            if (isset($oldMethods[$getterName])) {
                /** @var ClassMethod $oldMethod */
                $oldMethod = $oldMethods[$getterName];

                if ($oldMethod->getReturnType() !== null) {
                    if ($oldMethod->getReturnType() instanceof Name\FullyQualified) {
                        $className = '\\' . implode('\\', $oldMethod->getReturnType()->parts);
                        if (class_exists($className)) {
                            $returnTypeObjectClasses[md5($className)] = $className;
                        }
                    } elseif ($oldMethod->getReturnType() instanceof Name) {
                        $className = $currentNamespace . '\\' . implode('\\', $oldMethod->getReturnType()->parts);
                        if (class_exists($className)) {
                            $returnTypeObjectClasses[md5($className)] = $className;
                        }
                    } elseif ($oldMethod->getReturnType() instanceof UnionType) {
                        foreach ($oldMethod->getReturnType()->types as $type) {
                            $className = $currentNamespace . '\\' . implode('\\', $type->parts);
                            if (class_exists($className)) {
                                $returnTypeObjectClasses[md5($className)] = $className;
                            }
                        }
                    } elseif ($oldMethod->getReturnType() instanceof NullableType) {
                        foreach ($oldMethod->getReturnType()->type->parts as $type) {
                            $className = $currentNamespace . '\\' . $type;
                            if (class_exists($className)) {
                                $returnTypeObjectClasses[md5($className)] = $className;
                            }
                        }
                    } else {
                        throw new Exception('Unknown return type on field ' . $fieldToOverwrite);
                    }
                }
            }

            switch ($fieldProperties['type']) {
                case 'collection':
                    $getter = new ClassMethod($getterName);

                    $createArray = new Expression(
                        new Assign(
                            new Variable('objectFields'),
                            new Array_()
                        )
                    );

                    $getClassProperties = new Expression(
                        new StaticCall(
                            new Name('ObjectMapper'),
                            new Identifier('getClassProperties'),
                            [
                                new Arg(
                                    new FuncCall(
                                        new Name('get_class'),
                                        [
                                            new Arg(new Variable('this'))
                                        ]
                                    )
                                ),
                                new Arg(
                                    new Variable('objectFields')
                                ),
                                new Arg(
                                    new Array_(
                                        [
                                            new ArrayItem(
                                                new String_($fieldToOverwrite)
                                            )
                                        ]
                                    )
                                )
                            ]
                        )
                    );

                    $reflectionProperty = new Expression(
                        new Assign(
                            new Variable('reflectionProperty'),
                            new ArrayDimFetch(
                                new Variable('objectFields'),
                                new String_($fieldToOverwrite)
                            )
                        )
                    );

                    $visibilityLevel = new Expression(
                        new Assign(
                            new Variable('visibilityLevel'),
                            new StaticCall(
                                new Name('ObjectMapper'),
                                new Identifier('setFieldAccessible'),
                                [
                                    new Arg(
                                        new Variable('reflectionProperty')
                                    )
                                ]
                            )
                        )
                    );

                    $features = new Expression(
                        new Assign(
                            new Variable($fieldToOverwrite),
                            new MethodCall(
                                new Variable('reflectionProperty'),
                                new Identifier('getValue'),
                                [
                                    new Arg(
                                        new Variable('this')
                                    )
                                ]
                            )
                        )
                    );

                    $ifCondition = new If_(
                        new Instanceof_(
                            new Variable($fieldToOverwrite),
                            new Name('LazyCollection')
                        )
                    );

                    $ifStmt1 = new Expression(
                        new Assign(
                            new Variable('collection'),
                            new MethodCall(
                                new Variable($fieldToOverwrite),
                                new Identifier('getCollection')
                            )
                        )
                    );

                    $ifStmt2 = new Expression(
                        new StaticCall(
                            new Name('ObjectMapper'),
                            new Identifier('setOriginalAccessibility'),
                            [
                                new Arg(new Variable('reflectionProperty')),
                                new Arg(new Variable('visibilityLevel'))
                            ]
                        )
                    );

                    $ifStmt3 = new Return_(new Variable('collection'));

                    $elseCond = new Else_(
                        [
                            new Return_(
                                new Variable($fieldToOverwrite)
                            )
                        ]
                    );

                    $ifCondition->stmts[] = $ifStmt1;
                    $ifCondition->stmts[] = $ifStmt2;
                    $ifCondition->stmts[] = $ifStmt3;
                    $ifCondition->else = $elseCond;

                    $getter->stmts[] = $createArray;
                    $getter->stmts[] = $getClassProperties;
                    $getter->stmts[] = $reflectionProperty;
                    $getter->stmts[] = $visibilityLevel;
                    $getter->stmts[] = $features;
                    $getter->stmts[] = $ifCondition;

                    $getter->returnType = (isset($oldMethod)) ? $oldMethod->getReturnType() : null;

                    $class[0]->stmts[] = $getter;
                    break;

                case 'entity':
                    $getter = new ClassMethod($getterName);
                    $return = new Return_();

                    $arguments = [];
                    $arguments[] = new Arg(new Variable('this'));
                    $arguments[] = new Arg(new String_($fieldToOverwrite));

                    $staticCall = new StaticCall(new Name('LazyEntity'), 'getLazyEntity', $arguments);
                    $return->expr = $staticCall;
                    $getter->stmts[] = $return;
                    $getter->returnType = (isset($oldMethod)) ? $oldMethod->getReturnType() : null;

                    $class[0]->stmts[] = $getter;
                    break;
            }
        }

        return $returnTypeObjectClasses;
    }

    private static function createObject(string $proxyFileName, string $proxyClassName) : object
    {
        //TODO - ewentualnie rozszerzyć o argumenty konstruktora
        if (!class_exists($proxyClassName, false)) {
            require_once($proxyFileName);
        }

        return new $proxyClassName();
    }

    private static function createProxyClass(string $objectClass, EntityManager $entityManager) : array
    {
        $objectClassArray = explode('\\', $objectClass);
        $newProxyDirForFile = self::getNewNamespace($objectClass);
        $className = array_pop($objectClassArray);

        if (str_starts_with($objectClass, implode('\\', $newProxyDirForFile))) {
            $originalProxyClassName = $objectClass;
        } else {
            $originalProxyClassName = implode('\\', $newProxyDirForFile) . '\\' . $className;
        }

        $ormProxyDir = getcwd() . '/cache/' . implode('/', $newProxyDirForFile);
        if (!file_exists($ormProxyDir)) {
            mkdir($ormProxyDir, 0700, true);
        }

        $classConfiguration = $entityManager->loadClassConfiguration($objectClass);
        $originalFileTime = filemtime($classConfiguration['filePath']);
        $configFileTime = filemtime($classConfiguration['configFilePath']);

        $proxyFileName = $ormProxyDir . '/' . $className . '.php';
        if (file_exists($proxyFileName)) {
            $proxyFileTime = filemtime($proxyFileName);
            if (($proxyFileTime >= $originalFileTime) && ($proxyFileTime >= $configFileTime)) {
                return [$proxyFileName, $originalProxyClassName];
            }
        }

        $fieldsToOverwrite = self::filterFieldsByType($classConfiguration['fields'], ['entity', 'collection'], false);

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor());

        $classBody = file_get_contents($classConfiguration['filePath']);
        $nodes = $parser->parse($classBody);

        $nodeFinder = new NodeFinder();
        $methods = $nodeFinder->findInstanceOf($nodes, ClassMethod::class);

        $oldMethods = [];
        /** @var ClassMethod $method */
        foreach ($methods as $method) {
            $oldMethods[$method->name->name] = $method;
        }

        $usesArray = $nodeFinder->findInstanceOf($nodes, Use_::class);

        $oldUses = [];
        /** @var Use_ $uses */
        foreach ($usesArray as $uses) {
            foreach ($uses->uses as $use) {
                $oldUses[implode('\\', $use->name->parts)] = $uses;
            }
        }

        //remove all methods, we will add only getters to lazy-loaded properties
        $nodes = $traverser->traverse($nodes);

        $namespace = $nodeFinder->findInstanceOf($nodes, Namespace_::class);
        $class = $nodeFinder->findInstanceOf($nodes, Class_::class);
        /** @var Namespace_ $namespace0 */
        $namespace0 = $namespace[0];
        $currentNamespace = implode('\\', $namespace0->name->parts);

        self::modifyNamespace($objectClass, $namespace);
        self::addExtendingClassUse($objectClass, $namespace);
        $addedClasses = self::addUses($namespace, $oldUses);
        self::modifyClassExtends($objectClass, $class);
        self::addOrmInitializedProperty($class);
        $returnTypeObjectClasses = self::createGetters($fieldsToOverwrite, $class, $oldMethods, $currentNamespace);
        self::addReturnTypeUses($namespace, $returnTypeObjectClasses, $addedClasses);

        $prettyPrinter = new Standard();
        $newCode = $prettyPrinter->prettyPrintFile($nodes);

        file_put_contents($proxyFileName, $newCode);

        return [$proxyFileName, $originalProxyClassName];
    }

    public static function create(string $objectClass, EntityManager $entityManager) : object
    {
        [$proxyFileName, $originalProxyClassName] = self::createProxyClass($objectClass, $entityManager);
        $object = self::createObject($proxyFileName, $originalProxyClassName);

        $entityConfiguration = $entityManager->loadClassConfiguration(get_class($object));

        if (isset($entityConfiguration['lifecycle']['postCreate'])) {
            $staticMethodName = $entityConfiguration['lifecycle']['postCreate'];
            $staticMethodName($object);
        }

        return $object;
    }
}
