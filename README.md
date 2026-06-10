# NTI Platforma – Backend

Backendová časť projektu **NTI Platforma** postavená na **Laravel 13 (PHP 8.3)**.
Poskytuje REST API a aplikačnú logiku pre správu štipendijných žiadostí, organizácií,
tímov, mentoringu, konzultácií, notifikácií a reportov.

> Frontend je v **samostatnom repozitári** – tento repozitár obsahuje len backend.
> Pre účely hodnotenia je priložená aj kópia `docker-compose.yml`, takže backend je
> možné spustiť samostatne.

## Technológie

- PHP 8.3 / Laravel 13
- MySQL 8.0
- Redis 7
- Nginx
- Docker / Docker Compose
- Testy: Pest / PHPUnit

## Požiadavky

- [Docker](https://www.docker.com/) a Docker Compose
- *(alternatívne pre lokálny beh bez Dockeru: PHP 8.3+, Composer)*

## Spustenie cez Docker (odporúčané)

> V repozitári sú dva compose súbory, preto je dôležité vždy uviesť
> `-f docker-compose.yml`.

**1. Priprav `.env`:**

```bash
cp .env.example .env
```

V súbore `.env` nastav pripojenie na databázu a Redis v rámci Docker siete
(názvy hostov zodpovedajú službám v `docker-compose.yml`):

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=nti_db
DB_USERNAME=nti_user
DB_PASSWORD=nti_secret

REDIS_HOST=redis
```

**2. Zostav a spusti kontajnery:**

```bash
docker compose -f docker-compose.yml up -d --build
```

**3. Nainštaluj závislosti, vygeneruj aplikačný kľúč a spusti migrácie so seedmi:**

```bash
docker compose -f docker-compose.yml exec app composer install
docker compose -f docker-compose.yml exec app php artisan key:generate
docker compose -f docker-compose.yml exec app php artisan migrate --seed
```

**4. Aplikácia beží na:** <http://localhost:8000>

Zastavenie kontajnerov:

```bash
docker compose -f docker-compose.yml down
```

### Prehľad služieb

| Služba  | Popis                  | Port / URL              |
|---------|------------------------|-------------------------|
| `nginx` | webový server          | http://localhost:8000   |
| `app`   | PHP 8.3-FPM (Laravel)  | –                       |
| `mysql` | MySQL 8.0 (db `nti_db`)| 3306                    |
| `redis` | Redis 7                | 6379                    |

## Spustenie lokálne (bez Dockeru)

Predvolene sa použije SQLite, takže nie je potrebná samostatná databáza:

```bash
composer setup     # composer install + .env + key:generate + migrate + build
php artisan serve
```

Aplikácia beží na <http://localhost:8000>.

## Spustenie testov

```bash
# v Dockeri
docker compose -f docker-compose.yml exec app php artisan test

# lokálne
php artisan test
```

## Štruktúra projektu (výber)

- `app/Http/Controllers` – API a admin kontroléry
- `app/Models` – Eloquent modely (Organization, Team, Mentorship, Assignment, …)
- `app/Services` – aplikačná logika (napr. `AuditService`)
- `app/Notifications` – e-mailové a databázové notifikácie
- `database/migrations` – databázová schéma
- `database/seeders` – roly a oprávnenia
- `tests/Feature` – funkčné testy
