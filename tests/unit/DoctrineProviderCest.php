<?php

use RateHub\GraphQL\GraphContext;
use RateHub\GraphQL\Doctrine\DoctrineProvider;
use RateHub\GraphQL\Doctrine\DoctrineProviderOptions;

use Doctrine\DBAL\Schema\Table;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Schema;

use DoctrineGraph\Schema\GQL001\GQL001_Project;
use DoctrineGraph\Schema\GQL001\GQL001_User;
use DoctrineGraph\Schema\GQL001\GQL001_City;
use DoctrineGraph\Schema\GQL001\GQL001_Interest;
use DoctrineGraph\Schema\GQL001\GQL001_Province;
use DoctrineGraph\Schema\GQL001\GQL001_Location;

use Doctrine\DBAL\Types\Type;

class DoctrineProviderCest
{

	public function _before(){

		$em = TestDb::createEntityManager('./tests/_data/schemas/default');

        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);

        if(!Type::hasType('hstore'))
            Type::addType('hstore', Hstore::class);

		$this->_dropTable($em, 'gql001_project');
        $this->_dropTable($em, 'gql001_interest');
        $this->_dropTable($em, 'gql001_usercity');
		$this->_dropTable($em, 'gql001_user');
        $this->_dropTable($em, 'gql001_city');
        $this->_dropTable($em, 'gql001_province');
		$this->_dropTable($em, 'gql001_location');

        $this->_dropSequence($em, 'gql001_project_id_seq');
        $this->_dropSequence($em, 'gql001_user_id_seq');
        $this->_dropSequence($em, 'gql001_city_id_seq');
        $this->_dropSequence($em, 'gql001_interest_id_seq');

        $em->clear();

	}

	public function _dropTable($em, $table){

		$checkSql = "SELECT * FROM information_schema.tables WHERE table_name='" . $table . "'";
		$checkResult = $em->getConnection()->executeQuery($checkSql);

		if ($checkResult->fetch()) {
			$em->getConnection()->executeUpdate('DROP TABLE ' . $table);
		}

	}

    public function _dropSequence($em, $sequence){

        $checkSql = "SELECT * FROM information_schema.sequences WHERE sequence_name='" . $sequence . "'";
        $checkResult = $em->getConnection()->executeQuery($checkSql);

        if ($checkResult->fetch()) {
            $em->getConnection()->executeUpdate('DROP SEQUENCE ' . $sequence);
        }

    }

    public function _setupSchemaGQL001($em){

		$sm = $em->getConnection()->getSchemaManager();


		/* USER */
		$user = new Table("gql001_user");
		$user->addColumn('id', 'integer');
		$user->addColumn('created_at', 'datetime');
		$user->addColumn('big_int', 'bigint');
		$user->addColumn('details', 'json_array');
		$user->addColumn('key_values', 'hstore');
		$user->setPrimaryKey(['id']);
		$sm->createTable($user);

		$user_sequence = new \Doctrine\DBAL\Schema\Sequence('gql001_user_id_seq');

		$sm->createSequence($user_sequence);


		/* PROJECT */
		$project = new Table("gql001_project");
		$project->addColumn('id', 'integer');
		$project->addColumn('user_id', 'integer');
		$project->setPrimaryKey(['id']);
		$project->addForeignKeyConstraint('gql001_user', ['user_id'], ['id']);
		$sm->createTable($project);

		$project_sequence = new \Doctrine\DBAL\Schema\Sequence('gql001_project_id_seq');
		$sm->createSequence($project_sequence);


		/* PROVINCE */
		$province = new Table("gql001_province");
		$province->addColumn('code', 'string');
		$province->setPrimaryKey(['code']);
		$sm->createTable($province);


		/* LOCATION */
		$location = new Table("gql001_location");
		$location->addColumn('lat', 'integer');
		$location->addColumn('long', 'integer');
		$location->addColumn('name', 'string');
		$location->setPrimaryKey(['lat', 'long']);
		$sm->createTable($location);


        /* CITY */
        $city = new Table("gql001_city");
        $city->addColumn('id', 'integer');
        $city->addColumn('name', 'string');
        $city->addColumn('province', 'string');
		$city->addColumn('lat', 'integer');
		$city->addColumn('long', 'integer');
        $city->setPrimaryKey(['id']);
        $city->addForeignKeyConstraint('gql001_province', ['province'], ['code']);
		$city->addForeignKeyConstraint('gql001_location', ['lat', 'long'], ['lat', 'long']);
        $sm->createTable($city);

        $city_sequence = new \Doctrine\DBAL\Schema\Sequence('gql001_city_id_seq');
        $sm->createSequence($city_sequence);


        /* USER CITY */
        $usercity = new Table("gql001_usercity");
        $usercity->addColumn('user_id', 'integer');
        $usercity->addColumn('city_id', 'integer');
        $usercity->addForeignKeyConstraint('gql001_user', ['user_id'], ['id']);
        $usercity->addForeignKeyConstraint('gql001_city', ['city_id'], ['id']);
        $sm->createTable($usercity);


        /* INTEREST */
        $usercity = new Table("gql001_interest");
        $usercity->addColumn('id', 'integer');
        $usercity->addColumn('user_id', 'integer');
        $usercity->addForeignKeyConstraint('gql001_user', ['user_id'], ['id']);
        $sm->createTable($usercity);
        $interest_sequence = new \Doctrine\DBAL\Schema\Sequence('gql001_interest_id_seq');
        $sm->createSequence($interest_sequence);

    }

    public function _getGraphQLSchema($provider){

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => $provider->getQueries()
        ]);
        $mutatorType = new ObjectType([
            'name' => 'Mutator',
            'fields' => $provider->getMutators()
        ]);

        // Initialize the schema
        $schema = new Schema([
            'query' => $queryType,
            'mutation' => $mutatorType,
            'types' => $provider->getTypes()
        ]);

        return $schema;

    }

	/**
	 * Scenario:
	 * Filter = blacklist
	 *
	 * @param UnitTester $I
	 */
	public function case1 (UnitTester $I){

        $I->wantTo('Instantiate DoctrineProvider');

        $em = TestDb::createEntityManager('./tests/_data/schemas/default');

        $this->_setupSchemaGQL001($em);

        $option = new DoctrineProviderOptions();
        $option->scalars = [
            'datetime'  => \RateHub\GraphQL\Doctrine\Types\DateTimeType::class,
            'array'     => \RateHub\GraphQL\Doctrine\Types\ArrayType::class,
            'bigint'    => \RateHub\GraphQL\Doctrine\Types\BigIntType::class,
            'hstore'    => \RateHub\GraphQL\Doctrine\Types\HstoreType::class,
            'json'      => \RateHub\GraphQL\Doctrine\Types\JsonType::class
        ];
        $option->em = $em;

        $provider = new DoctrineProvider('TestProvider', $option);

        $types = $provider->getTypes();

        $I->assertEquals(12, count($types));

        $userType = $provider->getType('User');

        $I->assertNotNull($userType);
        $I->assertEquals("User", $userType->name); // Test the name annotation property
		$I->assertEquals("A User", $userType->description); // Test the description annotation property

        $projectType = $provider->getType('Project');

        $I->assertNotNull($projectType);
		$I->assertEquals("Project", $projectType->name); // Test the name annotation property
		$I->assertEquals("A Project", $projectType->description); // Test the description annotation property

        $schema = $this->_getGraphQLSchema($provider);

		$result = \GraphQL\GraphQL::execute(
			$schema,
			"{
			  __schema{
				types{
				  name
				}
			  }
			}",
			null,
			new GraphContext(),
			null
		);

		$types = array();
		foreach($result['data']['__schema']['types'] as $type){
			array_push($types, $type["name"]);
		}

		$I->assertContains('User', $types);
		$I->assertContains('User__List', $types);
		$I->assertContains('Project', $types);
		$I->assertContains('Project__List', $types);

		$result2 = \GraphQL\GraphQL::execute(
			$schema,
			"query testQuery{
				User{
					items{
						id
					}
				}	
			}",
			null,
			new GraphContext(),
			null
		);

		$I->assertEquals(0, count($result2["data"]["User"]["items"]));


     }

    /**
     * Scenario:
     * Query for project and include the
     *
     * @param UnitTester $I
     */
    public function case2 (UnitTester $I){

        $I->wantTo('Query for User and Project');

        $em = TestDb::createEntityManager('./tests/_data/schemas/default');

        $this->_setupSchemaGQL001($em);

        $option = new DoctrineProviderOptions();
        $option->scalars = [
            'datetime'  => \RateHub\GraphQL\Doctrine\Types\DateTimeType::class,
            'array'     => \RateHub\GraphQL\Doctrine\Types\ArrayType::class,
            'bigint'    => \RateHub\GraphQL\Doctrine\Types\BigIntType::class,
            'hstore'    => \RateHub\GraphQL\Doctrine\Types\HstoreType::class,
            'json'      => \RateHub\GraphQL\Doctrine\Types\JsonType::class
        ];
        $option->em = $em;

        $provider = new DoctrineProvider('TestProvider', $option);

        $user = new GQL001_User();
        $user->created_at = \DateTime::createFromFormat('Y/m/d H:i:s', '2000/1/1 01:00:00', new \DateTimeZone('UTC'));  // timestamp: 946688400
        $user->big_int = 2500000000;

        $details = [
            'phone'     => '555-5555',
            'mobile'    => '555-6000'
        ];

        $key_values = [
            'enabled'   => false,
            'greeting'  => 'Hello!'
        ];

        $user->details      = $details;
        $user->key_values   = $key_values;

        $em->persist($user);
        $em->flush();

        // Generate Multiple interests
        $interests = [];
        for($i = 0; $i < 5; $i++) {

            $interest = new GQL001_Interest();
            $interest->user = $user;
            $em->persist($interest);

            array_push($interests, $interest);

        }
        $em->flush();

        $project = new GQL001_Project();
        $project->user = $user;
        $em->persist($project);
        $em->flush();

        $province = new GQL001_Province();
        $province->code = 'ON';
        $em->persist($province);

        $location = new GQL001_Location();
        $location->lat 	= 25;
        $location->long = 35;
        $location->name = 'here';
        $em->persist($location);

        $city = new GQL001_City();
        $city->name = 'Toronto';
        $city->province = $province;
        $city->location = $location;
        $em->persist($city);

        $user->cities->add($city);
        $city->users->add($user);

        $em->flush();

        $schema = $this->_getGraphQLSchema($provider);

        $result = \GraphQL\GraphQL::execute(
            $schema,
            "{
			  User{
				items{
				  id
				  created_at
				  big_int
				  details
				  key_values
				  interests{
				    items{
				      id
				    }
				  }
				}
			  }
			}",
            null,
            new GraphContext(),
            null
        );

        $provider->clearBuffers();

        $I->assertEquals(1, count($result["data"]["User"]["items"]));

        $item = $result["data"]["User"]["items"][0];

        $I->assertEquals(1, $item['id']);
        $I->assertEquals(946688400, $item['created_at']);
        $I->assertSame("2500000000" , $item['big_int']); // Should come back as a string
        $I->assertEquals('555-5555', $item['details']['phone']);
        $I->assertEquals('Hello!', $item['key_values']->greeting);
        $I->assertEquals(5, count($item['interests']['items']));

        $result = \GraphQL\GraphQL::execute(
            $schema,
            "{
			  Project{
				items{
				  id
				  user{
				    id
				    cities{
				      items{
				        id
				        name
				      }
				    }
				  }
				}
			  }
			}",
            null,
            new GraphContext(),
            null
        );

        $provider->clearBuffers();

        $I->assertEquals(1, count($result["data"]["Project"]["items"]));

        $item = $result["data"]["Project"]["items"][0];

        $I->assertEquals(1, $item['id']);

        $I->assertEquals("Toronto", $item['user']['cities']['items'][0]['name']);


        // Retrieve a cities list of users
        $result = \GraphQL\GraphQL::execute(
            $schema,
            "{
			  City{
				items{
				  id
				  users{
				    items{
				      id
				      created_at
				    }  	
				  }
				  province{
				    code
				  }
				}
			  }
			}",
            null,
            new GraphContext(),
            null
        );

        $provider->clearBuffers();

        $I->assertEquals(1, count($result["data"]["City"]["items"]));

        $item = $result["data"]["City"]["items"][0];

        $I->assertEquals(1, $item['id']);

        $I->assertEquals('ON', $item['province']['code']);

        $I->assertEquals(1, count($item['users']['items']));

        $I->assertEquals(1, $item['users']['items'][0]['id']);



        // Retrieve a province with a list of cities
        $result = \GraphQL\GraphQL::execute(
            $schema,
            "{
			  Province{
				items{
				  code
				  cities{
				    items{
				      id
				      name
				      province {
				        code
				      }
				    }  	
				  }
				}
			  }
			}",
            null,
            new GraphContext(),
            null
        );

        $provider->clearBuffers();

        $I->assertEquals(1, count($result["data"]["Province"]["items"]));

        $province = $result["data"]["Province"]["items"][0];

        $I->assertEquals('ON', $province['code']);

        $I->assertEquals(1, count($province['cities']['items']));

        $city = $province['cities']['items'][0];

        $I->assertEquals('ON', $city['province']['code']);


		// Retrieve a province with a list of cities
		$result = \GraphQL\GraphQL::execute(
			$schema,
			"{
			  Location{
				items{
				  lat
				  long
				  cities{
				    items{
				      id
				      name
				      province {
				        code
				      }
				      location{
				        lat,
				        long
				        cities{
				          items{
				            id
				            name
				          }
				        }
				      }
				    }  	
				  }
				}
			  }
			}",
			null,
			new GraphContext(),
			null
		);

		$provider->clearBuffers();

		$I->assertEquals(1, count($result["data"]["Location"]["items"]));

		$location = $result["data"]["Location"]["items"][0];

		$I->assertEquals(1, count($location['cities']['items']));

		$city = $location['cities']['items'][0];

		$I->assertEquals(25, $city['location']['lat']);
		$I->assertEquals(35, $city['location']['long']);


	}

	/**
	 * Scenario:
	 * Create a record with the mutator
	 *
	 * @param UnitTester $I
	 */
	public function case3 (UnitTester $I){

		$I->wantTo('Create a location');

		$em = TestDb::createEntityManager('./tests/_data/schemas/default');

		$this->_setupSchemaGQL001($em);

		$option = new DoctrineProviderOptions();
		$option->scalars = [
			'datetime'  => \RateHub\GraphQL\Doctrine\Types\DateTimeType::class,
			'array'     => \RateHub\GraphQL\Doctrine\Types\ArrayType::class,
			'bigint'    => \RateHub\GraphQL\Doctrine\Types\BigIntType::class,
			'hstore'    => \RateHub\GraphQL\Doctrine\Types\HstoreType::class,
			'json'      => \RateHub\GraphQL\Doctrine\Types\JsonType::class
		];
		$option->em = $em;

		$provider = new DoctrineProvider('TestProvider', $option);

		$schema = $this->_getGraphQLSchema($provider);

		$locations = [[
			"lat" => 20,
			"long" => 25,
			"name" => "test"
		]];

		$result = \GraphQL\GraphQL::execute(
			$schema,
			'mutation CreateLocation($items: [Location__Input]){
			  create_Location(items: $items){
			  	lat
			  	long
			  }
			}',
			null,
			new GraphContext(),
			['items' => $locations]
		);

		$provider->clearBuffers();

		$I->assertEquals(1, count($result));

		$I->assertEquals(1, count($result["data"]["create_Location"]));

		$location = $result["data"]["create_Location"][0];

		$I->assertEquals(20, $location["lat"]);
		$I->assertEquals(25, $location["long"]);

	}

	/**
	 * Scenario:
	 * Update a record with the mutator
	 *
	 * @param UnitTester $I
	 */
	public function case4 (UnitTester $I){

		$I->wantTo('Update a location');

		$em = TestDb::createEntityManager('./tests/_data/schemas/default');

		$this->_setupSchemaGQL001($em);

		$location = new GQL001_Location();
		$location->lat 	= 25;
		$location->long = 35;
		$location->name = 'here';
		$em->persist($location);
		$em->flush();

		$option = new DoctrineProviderOptions();
		$option->scalars = [
			'datetime'  => \RateHub\GraphQL\Doctrine\Types\DateTimeType::class,
			'array'     => \RateHub\GraphQL\Doctrine\Types\ArrayType::class,
			'bigint'    => \RateHub\GraphQL\Doctrine\Types\BigIntType::class,
			'hstore'    => \RateHub\GraphQL\Doctrine\Types\HstoreType::class,
			'json'      => \RateHub\GraphQL\Doctrine\Types\JsonType::class
		];
		$option->em = $em;

		$provider = new DoctrineProvider('TestProvider', $option);

		$schema = $this->_getGraphQLSchema($provider);

		$locations = [[
			"lat" => 25,
			"long" => 35,
			"name" => "updatedname"
		]];

		$result = \GraphQL\GraphQL::execute(
			$schema,
			'mutation UpdateLocation($items: [Location__Input]){
			  update_Location(items: $items){
			  	lat
			  	long
			  	name
			  }
			}',
			null,
			new GraphContext(),
			['items' => $locations]
		);

		$provider->clearBuffers();

		$I->assertEquals(1, count($result));

		$I->assertEquals(1, count($result["data"]["update_Location"]));

		$location = $result["data"]["update_Location"][0];

		$I->assertEquals(25, $location["lat"]);
		$I->assertEquals(35, $location["long"]);
		$I->assertEquals("updatedname", $location["name"]);

	}

	/**
	 * Scenario:
	 * Delete a record with the mutator
	 *
	 * @param UnitTester $I
	 */
	public function case5 (UnitTester $I){

		$I->wantTo('Delete a location');

		$em = TestDb::createEntityManager('./tests/_data/schemas/default');

		$this->_setupSchemaGQL001($em);

		$location = new GQL001_Location();
		$location->lat 	= 25;
		$location->long = 35;
		$location->name = 'here';
		$em->persist($location);
		$em->flush();

		$option = new DoctrineProviderOptions();
		$option->scalars = [
			'datetime'  => \RateHub\GraphQL\Doctrine\Types\DateTimeType::class,
			'array'     => \RateHub\GraphQL\Doctrine\Types\ArrayType::class,
			'bigint'    => \RateHub\GraphQL\Doctrine\Types\BigIntType::class,
			'hstore'    => \RateHub\GraphQL\Doctrine\Types\HstoreType::class,
			'json'      => \RateHub\GraphQL\Doctrine\Types\JsonType::class
		];
		$option->em = $em;

		$provider = new DoctrineProvider('TestProvider', $option);

		$schema = $this->_getGraphQLSchema($provider);

		$locations = [[
			"lat" => 25,
			"long" => 35,
		]];

		$result = \GraphQL\GraphQL::execute(
			$schema,
			'mutation DeleteLocation($items: [Location__Input]){
			  delete_Location(items: $items)
			}',
			null,
			new GraphContext(),
			['items' => $locations]
		);

		$provider->clearBuffers();

		$I->assertEquals(1, count($result));

		$I->assertEquals(true, $result["data"]["delete_Location"]);

	}

}