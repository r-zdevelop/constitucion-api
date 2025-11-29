# Project Structure

```
constitucion-api/
├── bin/
│   └── console
├── config/
│   ├── packages/
│   │   ├── cache.yaml
│   │   ├── doctrine_migrations.yaml
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   └── routing.yaml
│   ├── routes/
│   │   └── framework.yaml
│   ├── bundles.php
│   ├── preload.php
│   ├── routes.yaml
│   └── services.yaml
├── data/
│   └── constitucion.json
├── migrations/
│   ├── .gitignore
│   ├── Version20251119220232.php
│   ├── Version20251119234105.php
│   ├── Version20251119234144.php
│   └── Version20251119235720.php
├── public/
│   └── index.php
├── src/
│   ├── Command/
│   │   └── ImportConstitutionCommand.php
│   ├── Controller/
│   │   └── .gitignore
│   ├── Entity/
│   │   ├── .gitignore
│   │   ├── Article.php
│   │   ├── ArticleHistory.php
│   │   ├── Concordance.php
│   │   ├── DocumentSection.php
│   │   └── LegalDocument.php
│   ├── Repository/
│   │   ├── .gitignore
│   │   ├── ArticleRepository.php
│   │   └── ArticleRepositoryInterface.php
│   ├── Service/
│   │   └── ArticleService.php
│   └── Kernel.php
├── .editorconfig
├── .env
├── .env.dev
├── .gitignore
├── compose.override.yaml
├── compose.yaml
├── composer.json
├── composer.lock
├── schema.sql
└── symfony.lock
```
