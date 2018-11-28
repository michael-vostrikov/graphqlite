<?php

namespace TheCodingMachine\GraphQL\Controllers\Mappers;

use Doctrine\Common\Annotations\AnnotationReader;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use Mouf\Picotainer\Picotainer;
use Symfony\Component\Cache\Simple\NullCache;
use TheCodingMachine\GraphQL\Controllers\AbstractQueryProviderTest;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\ClassA;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\ClassB;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\ClassC;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\Types\ClassAType;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\Types\ClassBType;
use TheCodingMachine\GraphQL\Controllers\Fixtures\TestObject;
use TheCodingMachine\GraphQL\Controllers\TypeGenerator;

class RecursiveTypeMapperTest extends AbstractQueryProviderTest
{

    public function testMapClassToType()
    {
        $objectType = new ObjectType([
            'name' => 'Foobar'
        ]);

        $typeMapper = new StaticTypeMapper();
        $typeMapper->setTypes([
            ClassB::class => $objectType
        ]);

        $recursiveTypeMapper = new RecursiveTypeMapper($typeMapper);

        $this->assertFalse($typeMapper->canMapClassToType(ClassC::class));
        $this->assertTrue($recursiveTypeMapper->canMapClassToType(ClassC::class));
        $this->assertSame($objectType, $recursiveTypeMapper->mapClassToType(ClassC::class));

        $this->assertFalse($recursiveTypeMapper->canMapClassToType(ClassA::class));
        $this->expectException(CannotMapTypeException::class);
        $recursiveTypeMapper->mapClassToType(ClassA::class);
    }

    public function testMapClassToInputType()
    {
        $inputObjectType = new InputObjectType([
            'name' => 'Foobar'
        ]);

        $typeMapper = new StaticTypeMapper();
        $typeMapper->setInputTypes([
            ClassB::class => $inputObjectType
        ]);

        $recursiveTypeMapper = new RecursiveTypeMapper($typeMapper);

        $this->assertFalse($recursiveTypeMapper->canMapClassToInputType(ClassC::class));

        $this->expectException(CannotMapTypeException::class);
        $recursiveTypeMapper->mapClassToInputType(ClassC::class);
    }

    protected function getTypeMapper()
    {
        $container = new Picotainer([
            ClassAType::class => function() {
                return new ClassAType();
            },
            ClassBType::class => function() {
                return new ClassBType();
            }
        ]);

        $typeGenerator = new TypeGenerator($this->getRegistry());

        $mapper = new GlobTypeMapper('TheCodingMachine\GraphQL\Controllers\Fixtures\Interfaces\Types', $typeGenerator, $container, new \TheCodingMachine\GraphQL\Controllers\AnnotationReader(new AnnotationReader()), new NullCache());

        return new RecursiveTypeMapper($mapper);
    }

    public function testMapClassToInterfaceOrType()
    {
        $recursiveMapper = $this->getTypeMapper();

        $type = $recursiveMapper->mapClassToInterfaceOrType(ClassA::class);
        $this->assertInstanceOf(InterfaceType::class, $type);
        $this->assertSame('ClassAInterface', $type->name);

        $classAObjectType = $recursiveMapper->mapClassToType(ClassA::class);
        $this->assertInstanceOf(ObjectType::class, $classAObjectType);
        $this->assertCount(1, $classAObjectType->getInterfaces());

        $type = $recursiveMapper->mapClassToInterfaceOrType(ClassC::class);
        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertSame('ClassB', $type->name);

        $interfaces = $recursiveMapper->findInterfaces(ClassC::class);
        $this->assertCount(1, $interfaces);
        $this->assertSame('ClassAInterface', $interfaces[0]->name);

        $classBObjectType = $recursiveMapper->mapClassToType(ClassC::class);
        $this->assertInstanceOf(ObjectType::class, $classBObjectType);
        $this->assertCount(1, $classBObjectType->getInterfaces());

        $this->expectException(CannotMapTypeException::class);
        $recursiveMapper->mapClassToInterfaceOrType('Not exists');
    }
}
