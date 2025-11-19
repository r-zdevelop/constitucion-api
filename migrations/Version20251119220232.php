<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119220232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_history (id INT UNSIGNED AUTO_INCREMENT NOT NULL, content_before LONGTEXT NOT NULL, content_after LONGTEXT NOT NULL, modified_by VARCHAR(100) NOT NULL, modification_reason VARCHAR(255) DEFAULT NULL, modified_at DATETIME NOT NULL, article_id INT UNSIGNED NOT NULL, INDEX IDX_CA6834FC7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE articles (id INT UNSIGNED AUTO_INCREMENT NOT NULL, article_number INT NOT NULL, content LONGTEXT NOT NULL, title VARCHAR(255) DEFAULT NULL, chapter VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, document_id INT UNSIGNED NOT NULL, section_id INT UNSIGNED DEFAULT NULL, INDEX IDX_BFDD3168C33F7837 (document_id), INDEX IDX_BFDD3168D823E37A (section_id), UNIQUE INDEX unique_article (document_id, article_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE concordances (id INT UNSIGNED AUTO_INCREMENT NOT NULL, referenced_law VARCHAR(255) NOT NULL, referenced_articles JSON NOT NULL, created_at DATETIME NOT NULL, article_id INT UNSIGNED NOT NULL, INDEX IDX_4810B9427294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_sections (id INT UNSIGNED AUTO_INCREMENT NOT NULL, section_type VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, order_index INT NOT NULL, created_at DATETIME NOT NULL, document_id INT UNSIGNED NOT NULL, parent_section_id INT UNSIGNED DEFAULT NULL, INDEX IDX_A457B2C1C33F7837 (document_id), INDEX IDX_A457B2C19F60672A (parent_section_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE legal_documents (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, document_type VARCHAR(32) NOT NULL, year INT NOT NULL, last_modified DATE NOT NULL, total_articles INT UNSIGNED NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article_history ADD CONSTRAINT FK_CA6834FC7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168C33F7837 FOREIGN KEY (document_id) REFERENCES legal_documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168D823E37A FOREIGN KEY (section_id) REFERENCES document_sections (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE concordances ADD CONSTRAINT FK_4810B9427294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_sections ADD CONSTRAINT FK_A457B2C1C33F7837 FOREIGN KEY (document_id) REFERENCES legal_documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_sections ADD CONSTRAINT FK_A457B2C19F60672A FOREIGN KEY (parent_section_id) REFERENCES document_sections (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_history DROP FOREIGN KEY FK_CA6834FC7294869C');
        $this->addSql('ALTER TABLE articles DROP FOREIGN KEY FK_BFDD3168C33F7837');
        $this->addSql('ALTER TABLE articles DROP FOREIGN KEY FK_BFDD3168D823E37A');
        $this->addSql('ALTER TABLE concordances DROP FOREIGN KEY FK_4810B9427294869C');
        $this->addSql('ALTER TABLE document_sections DROP FOREIGN KEY FK_A457B2C1C33F7837');
        $this->addSql('ALTER TABLE document_sections DROP FOREIGN KEY FK_A457B2C19F60672A');
        $this->addSql('DROP TABLE article_history');
        $this->addSql('DROP TABLE articles');
        $this->addSql('DROP TABLE concordances');
        $this->addSql('DROP TABLE document_sections');
        $this->addSql('DROP TABLE legal_documents');
    }
}
