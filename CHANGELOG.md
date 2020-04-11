Changelog
=========

1.8.0 (2020-04-11)
------------------
- add new "whereEscape()" method
- use db->escape()

1.7.1 (2020-01-27)
------------------
- fix bugs reported by phpstan

1.7.0 (2020-01-27)
------------------
- update vendor libs
- do less magic

1.6.0 (2019-11-29)
------------------
- update vendor libs
- throw "TypeError" on type errors, defined via phpdoc comments

1.5.0 (2019-11-21)
------------------
- update vendor libs
- CollectionActiveRecord::createFromGeneratorFunction()

1.4.0 (2019-08-24)
------------------
- add "fetchOneByQueryOrThrowException()"
- check if "fetchOneByQuery()" fetched only one entry, otherwise throw a exception

1.3.0 (2019-08-15)
------------------
- add "yield" support for all non-single fetch methods

1.2.0 (2019-06-25)
------------------
- add "Collection" for collection of ActiveRecord results + type check
- replace strings in Expressions with class constants

1.1.0 (2019-04-21)
------------------
- add "ActiveRecord" class const for SQL building
- add a "ActiveRecord->addRelation()" method + examples in the README
- add support for "Hashids" - generate YouTube-like ids from numbers. Use it when you don't want to expose your database numeric ids to users
  -> add "ActiveRecord->getHashId()": convert primary-key into hashid
  -> add "ActiveRecord->convertIdIntoHashId()": convert any id into hashid
  -> add "ActiveRecord->fetchByHashId()": same as fetchBy() but with a hashid parameter
  -> add "ActiveRecord->fetchByHashIdIfExists()": same as fetchByIfExists() with a hashid parameter
- fix bugs reported by phpstan (level 7)

1.0.0 (2018-12-21)
------------------

INFO: There was no breaking API changes, so you can easily upgrade from "Simple MySQLi" to "Simple Active Record"

- init
