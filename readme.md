#1. Co to jest orm
-----------------
Orm jest biblioteką wspomagającą translację obiektów utworzonych w php, na rekordy relacyjnej bazy danych, i odwrotnie,
tzn. za pomocą tej biblioteki można dokonać translacji rekordów bazy danych na obiekty php. Biblioteka umożliwia także
generowanie zapytań aktualizujących strukturę bazy danych do stanu zastanego w konfiguracji poszczególnych klas (migracje),
a także automatyczne tworzenie obiektów, np. wymaganych wartości słownikowych dla nowych systemów.
Na chwilę obecną biblioteka współpracuje jedynie z bazą danych MySQL. Nic nie stoi jednak na przeszkodzie, aby zaimplementować
własną bibliotekę do obsługi dowolnej bazy danych.

#2. Jak zainstalować orm
-----------------------
Instalacja biblioteki jest bardzo prosta i można jej dokonać za pomocą Composera. Jeśli znalazłeś ją na githubie, to
powinieneś wskazać, skąd Composer powinien pobrać kod biblioteki. W tym celu do pliku composer.json należy dodać wskazanie
na repozytorium, które Composer powinien przeszukiwać w poszukiwaniu bibliotek:


```yml
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/mstodulski/orm.git"
  }
]
```

Następnie, w tym samym pliku, należy dodać bibliotekę do sekcji "require":

```yml
"require": {
   "mstodulski/orm": "1.0.*",
}
```

Potem wystarczy zaktualizować biblioteki poleceniem:

```
php composer update
```

Jeśli bibliotekę znalazłeś na packagist.org, to wystarczy wykonać polecenie:

```
php composer require mstodulski/orm
```

Biblioteka zainstaluje się automatycznie i w obu przypadkach, o ile nie zostało to skonfigurowane inaczej, zostanie
zainstalowana w katalogu "vendor".

Nie zapomnij dołączyć do swojego pliku index.php pliku /vendor/autoload.php, aby automatycznie ładować klasy bibliotek
zainstalowanych Composerem.

#3. Jak skonfigurować orm
------------------------
Aby wykorzystywać bibliotekę orm, należy ją najpierw skonfigurować. Entry-pointem do wszelkich funkcjonalności, wchodzących
w skład biblioteki, jest klasa EntityManager, i to obiekt tej klasy musimy utworzyć, aby móc robić cokolwiek innego.
W tym celu należy utworzyć tablicę, zawierającą następujące klucze:

```$config['dsn'] = 'mysql:host=localhost;port=3306;dbname=orm;charset=utf8;';``` - nazwa źródła danych, które posłuży nam do
współpracy z bazą danych

```$config['user'] = 'root';``` - nazwa użytkownika bazy danych. Pamiętaj, że użytkownika root możemy bezpiecznie używać tylko
na lokalnym komputerze z niewystawionym na zewnątrz serwerem http ani bazą danych.

```$config['password'] = null;``` - hasło użytkownika bazy danych. Pamiętaj, że hasło może być puste tylko na lokalnym komputerze
z niewystawionym na zewnątrz serwerem http ani bazą danych.

```$config['entityConfigurationDir'] = 'tests/config/';``` - ścieżka do katalogu, w którym będziemy przechowywać pliki *.orm.yml,
w których będzie zapisana konfiguracja poszczególnych klas, pozwalająca na współpracę z bazą danych

```$config['migrationDir'] = 'tests/migrations/';``` - ścieżka do katalogu, w którym będą tworzone migracje do aktualizowania
struktury bazy danych. Z tego katalogu będą one również pobierane przy wykonywaniu aktualizacji struktury.

```$config['fixtureDir'] = 'tests/fixtures/';``` - ścieżka do katalogu, w którym będą przechowywane pliki *.yml, zawierające
dane niezbędne do automatycznego utworzenia nowych obiektów w bazie danych, np. danych słownikowych przy instalacji
czystego systemu.

```$config['mode'] = 'prod';``` - tryb pracy biblioteki, może przyjmować wartości "prod" lub "dev". W przypadku ustawienia "prod"
dane, które mogą zostać wykorzystane ponownie (ale nie dane z bazy danych, tylko dane takie jak konfiguracja poszczególnych
klas, klasy proxy, itp), są zapisywane do cache w celu uzyskania szybszego dostępu do nich. W przypadku ustawienia "dev"
wszystkie konfiguracje klas i pozostałe dane są pobierane za każdym razem z odpowiedniego źródła.

Kolejną rzeczą, którą musimy mieć, jest klasa tworząca konkretne zapytania dla konkretnej bazy danych. W przypadku bazy
MySQL jest to klasa dołączona do biblioteki, o nazwie mstodulski\database\MySQLAdapter

Mając konfigurację oraz klasę do bezpośredniej wpółpracy z bazą danych możemy utworzyć obiekt klasy EntityManager:

```php
$mysqlAdapter = new mstodulski\database\MySQLAdapter();
$entityManager = mstodulski\database\EntityManager::create($mysqlAdapter, $config);
```

W tym momencie mamy już obiekt klasy EntityManager, którym możemy tworzyć obiekty repozytorów do prostego pobierania danych
z bazy, obiekt query buildera do pobierania obiektów z bazy opartego o bardziej skomplikowane warunki. Za pomocą tego obiektu
możemy również zapisywać lub usuwać rekordy z bazy danych.

#4. Jak utworzyć encję i jej konfigurację
----------------------------------------

Pierwszym krokiem do utworzenia encji powinna być jej konfiguracja. W tym celu w katalogu, który został w tablicy konfiguracyjnej
zdefiniowany jako "entityConfigurationDir" powinniśmy utworzyć plik o nazwie [nazwaKlasy].orm.yml. W naszym przypadku może
to być na przykład User.orm.yml.
Pierwszym krokiem powinno być określenie klasy danej encji, u nas to będzie klasa User. Powinno to wyglądać następująco:

```yml
entity: test\orm\helpers\User
```

Kolejnym obowiązkowym krokiem jest ustalenie, która klasa repozytorium będzie używana do obsługi tej encji. Obiekt określonej
w tym miejscu klasy będzie odpowiedzialny za pobieranie obiektu klasy, którą konfigurujemy, z bazy danych. O ile nie planujemy
rozszerzać mechanizmu pobierania o nowe funkcje, lub nadpisywać istniejących, to powinna być to domyślna klasa repozytorium,
dostępna w bibliotece:

```yml
repository: mstodulski\database\Repository
```

Ostatnim na tym etapie krokiem jest określenie, jakie pola będą znajdowały się w danej encji. Pola definiujemy w kluczu "fields":

```yml
fields:
```

Definicja pola w minimalnej konfiguracji powinna zawierać typ pola. Typy pól dzielą się na typy proste, zawierające konkretną
wartość, oraz typy pomocnicze, wskazujące, że wartościa jest kolekcja innych obiektów, lub klucz obcy (odnośnik od innej
encji). Typy proste są zbieżne z typami z bazy MySQL, i należą do nich:

```tinyint``` - pole przechowujące liczbę całkowitą od 0 do 255 bez znaku lub od -127 do 127 ze znakiem.<br/>
```smallint``` - pole przechowujące liczbę całkowitą od 0 do 65535 bez znaku lub od -32768 do 32768 ze znakiem.<br/>
```mediumint``` - pole przechowujące liczbę całkowitą od 0 do 16777215 bez znaku lub od -8388608 do 8388608 ze znakiem.<br/>
```int``` - pole przechowujące liczbę całkowitą od 0 do 4294967295 bez znaku lub od -2147483647 do 2147483647 ze znakiem.<br/>
```bigint``` - pole przechowujące liczbę całkowitą od 0 do 2^64 - 1 bez znaku lub od -2^63 do 2^63 - 1 ze znakiem.<br/>
```float``` - liczba zmiennoprzecinkowa, możliwa do zapisania w 4 bajtach<br/>
```double``` - liczba zmiennoprzecinkowa, możliwa do zapisania w 8 bajtach (podwójna precyzja)<br/>
```decimal``` - liczba zmiennoprzecinkowa o określonej precyzji, domyślnie jest to 20 miejsc przed przecinkiem i 6 po przeciku<br/>
```varchar``` - pole przechowuje wartość tekstową o rozmiarze do 255 bajtów<br/>
```char``` - pole przechowuje pojedynczy znak<br/>
```tinytext``` - pole przechowuje wartość tekstową o rozmiarze nie przekraczającym 255 bajtów<br/>
```text``` - pole przechowuje wartość tekstową o rozmiarze nie przekraczającym 65535 bajtów<br/>
```mediumtext``` - pole przechowuje wartość tekstową o rozmiarze nie przekraczającym 16777215 bajtów<br/>
```longtext``` - pole przechowuje wartość tekstową o rozmiarze nie przekraczającym 4294967295 bajtów<br/>
```date``` - pole przechowujące datę<br/>
```datetime``` - pole przechowujące datę i czas<br/>
```boolean``` - pole przechowujące wartość logiczną (true lub false)

Typy pomocnicze zostaną omówione w dalszej części tekstu.
Najważniejszym polem jest oczywiście pole z identyfikatorem danej encji. Pole to nazywamy domyślnie id i nadamy mu typ int.
Oprócz tego musimy oznaczyć, że jest to właśnie identyfikator, oraz opcjonalnie nadać mu atrybut AUTO_INCREMENT, aby wartość
w tym polu zwiększała się automatycznie dla każdego nowego rekordu. Powinno to wyglądać tak:

```yml
fields:
  id:
    type: int
    id: true
    extra: auto_increment
```

Następnie dodamy jeszcze nazwę użytkownika, pole powinno się nazywać "name" i przechowywać krótkie wartości tekstowe:

```yml
fields:
  id:
    type: int
    id: true
    extra: auto_increment
  name:
    type: varchar
```

Pola mogą umożliwiać przechowywanie wartości NULL, ale domyślnie są skonfigurowane tak, aby nie mogły przyjmować tej
wartości. Aby zmienić to ustawienie, powinniśmy ustawić dla pola cechę "nullable" na true:

```yml
fields:
  id:
    type: int
    id: true
    extra: auto_increment
  name:
    type: varchar
    nullable: true
```

Całość naszego pliku konfiguracyjnego User.orm.yml powinna wyglądać następująco (zrezygnujemy z możliwości ustawienia w
polu "name" wartości null, aby była zawsze konieczność ustawienia nazwy użytkownika:

```yml
entity: test\orm\helpers\User
repository: mstodulski\database\Repository
fields:
  id:
    type: int
    id: true
    extra: auto_increment
  name:
    type: varchar
```

Kolejnym krokiem jest utworzenie klasy encji w php. Powinna znajdować się w pliku User.php:

```php
<?php
class User
{
    private ?int $id = null;
    private string $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
```

Z pewnością zauważyłeś, że brakuje tam settera do pola $id - nie jest nam potrzebny, wartość temu polu nadaje biblioteka
po zapisaniu lub odczycie obiektu z bazy.
Gdy już mamy utworzoną zarówno klasę encji, jak i jej konfigurację, możemy uruchomić w konsoli polecenie, które utworzy
nam plik zawierający zapytanie tworzące w bazie danych tabelę dla naszej encji. Polecenie to uruchamia plik orm, znajdujący
się w katalogu /bin, przekazuje do skryptu opcje konfiguracyjne, i nakazuje mu utworzenie migracji. Wartości w nawiasach
kwadratowych należy zastąpić swoimi wartościami:

```
php bin/orm -dsn [dsn] -u [user] -p [password] -cd [entityConfigurationDir] -md [migrationDir] -fd [fixtureDir] -ac mstodulski\database\MySQLAdapter generate migration
 ```

Wartości w nawiasach odpowiadają kluczom konfiguracyjnym, które zostały opisane w punkcie "Jak skonfigurować orm". Natomiast
instrukcja "generate migration" wskazuje skryptowi konieczność utworzenia migracji.

Przykładowe wywołanie:
```
php bin/orm -dsn "mysql:host=localhost;port=3306;dbname=orm;charset=utf8" -u root -p -cd tests/config -md tests/migrations -fd tests/fixtures -ac "mstodulski\database\MySQLAdapter" generate migration
```

Po wydaniu tego polecenia w katalogu skonfigurowanym jako [migrationDir] pojawi się plik MigrationXXXXXXX.php, w którym
będą zapisane zapytania, które należy wykonać na bazie danych. Migrację uruchamiamy instrukcją:

```
php bin/orm -dsn [dsn] -u [user] -p [password] -cd [entityConfigurationDir] -md [migrationDir] -fd [fixtureDir] -ac mstodulski\database\MySQLAdapter migrate
```

Po wykonaniu powyższej komendy w bazie danych zostanie utworzona tabela "user".

#5. Jak zapisać encję do bazy danych
-----------------------------------

To akurat jest bardzo prosta operacja. Po utworzeniu konfiguracji klasy User, oraz samej klasy User, musimy utworzyć obiekt
tej klasy i nadać wartości jego polom, aby móc go zapisać do bazy danych. Kolejnym krokiem jest wskazanie, że obiekt należy
utrwalić w bazie danych, robimy to za pomocą metody persist() obiektu klasy EntityManager. Możemy w ten sposób przekazać
do utrwalenia kilka obiektów, ponieważna tym etapie nie są one jeszcze zapisywane w bazie. Do zapisania w bazie służy
metoda flush() obiektu klasy EntityManager. Po wywołaniu tej metody rozpoczynana jest transakcja z bazą danych, a potem
wykonywane są zapisy poszczególnych obiektów. Gdy wszystko przebiegnie poprawnie, transakcja jest zatwierdzana, a obiekty
są widoczne w bazie danych. Gdy coś pójdzie nie tak przy zapisie dowolnego obiektu, to transakcja jest anulowana, i żaden
z obiektów ni eznajdzie się w bazie danych.
Cały proces zapisu dwóch obiektów klasy User może wyglądać tak (pominąłem tutaj etap tworzenia obiektu klasy EntityManager,
jak go utworzyć można przeczytać w punkcie 3):

```php
$user = new User();
$user->setName('user1');
$entityManager->persist($user);

$user2 = new User();
$user2->setName('user2');
$entityManager->persist($user2);

$entityManager->flush();
```

Po wykonaniu tej operacji w bazie danych, w tabeli user powinny znaleźć się dwa rekordy, które próbowaliśmy zapisać.

#6. Jak odczytać encję z bazy danych
------------------------------------

Na to jest kilka sposobów, w zależności od tego, co tak naprawdę chcemy odczytać z bazy, oraz jaki jest stopień złożoności
warunków, wg. których chcemy pobradane.

a. Pobranie obiektu danej klasy po jego id
Możemy to zrobić na dwa sposoby:
- bezpośrednio za pomocą EntityManagera:
```php
$user = $entityManager->find(User::class, 1);
```

- tworząc obiekt repozytorium właściwy dla danego obiektu:

```php
$repository = $entityManager->createRepository(User::class);
$user = $repository->find(1);
```

Dla metod find*(), zarówno EntityManagera jak i Repozytorium, ostatnim parametrem jest $hydrationMode, który służy nam do
określania, czy z bazy danych zostanie pobrany obiekt php ($hydrationMode powinno przyjąć wtedy wartość HydrationMode::Object,
jest to domyślne ustawienie), lub czy z bazy danych zostanie pobrana tablica ($hydrationMode powinno przyjąć wtedy wartość
HydrationMode::Array, przy takim ustawieniu pomijamy etap translacji rekordu na obiekt, a więc przyspieszamy operację).

Jeśli obiekt nie zostanie odnaleziony, to zostanie zwrócona wartość null.

b. Pobranie obiektu danej klasy po jego innych właściwościach:
Tutaj również istnieją dwa sposoby, albo bezpośrednio za pomocą EntityManagera, albo za pomocą dedykowanego klasie
repozytorum. W dalszej części tekstu ograniczę się do przykładów pobrania za pomocą EntityManagera. Poniższy kod wyszuka
jednego użytkownika na podstawie jego nazwy. Gdyby użytkowników o takiej nazwie było więcej, to zostanie zwrócony pierwszy
z nich:

```php
$user = $entityManager->findOneBy(User::class, ['name' => 'user1']);
```

Możliwe jest też pobranie usera po kilku parametrach, np. po name i id:

```php
$user = $entityManager->findOneBy(User::class, ['name' => 'user1', 'id' => 1]);
```

Możliwe jest również przekazanie sortowania, które zostanie zastosowane przy pobieraniu obiektu. Ma to wpływ na to, który
rekord zostanie napotkany jako pierwszy, a więc na to, który zostanie zwrócony:

```php
$user = $entityManager->findOneBy(User::class, ['name' => 'user1'], ['id' => 'DESC']);
```

TUTAJ PISZEMY DALEJ

#7. Jak dodać prostą relację z innym obiektem lub obiektami
----------------------------------------------------------
entity - wskazuje, że pole zawiera identyfikator innej encji. Na etapie translacji rekordu z bazy na obiekt php w tym polu
znajdzie się ta inna encja o zadanym id, lub obiekt klasy LazyEntity, w zależności od konfiguracji.
collection - wskazuje, że pole zawiera kolekcję innych obiektów. Na etapie translacji rekordu z bazy na obiekt php w tym
polu znajdzie się kolekcja encji, pobrana z bazy na podstawie innych parametrów pola, lub obiekt klasy LazyCollection, w
zależności od konfiguracji.

#8. Jak dodać relację manyToMany do obiektu
-------------------------------------------

#9. Indeksy i klucze obce
-------------------------
 - feature.orm.yml
 - product.orm.yml

#10. Eventy
-----------
- product.orm.yml


#11. Bezpośrednie wywołanie zapytań
-----------------------------------
$dbConnection->executeQuery(), getTable, getSingleRow, getValue

#12. Polecenia konsolowe