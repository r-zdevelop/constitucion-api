-- Database Schema for Constitución del Ecuador
-- MySQL 8.x compatible

CREATE DATABASE IF NOT EXISTS constitucion_ec
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE constitucion_ec;

-- Legal Documents (escalable para otros códigos)
CREATE TABLE legal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    document_type ENUM('constitution', 'code', 'law', 'regulation') DEFAULT 'constitution',
    year YEAR NOT NULL,
    last_modified DATE NOT NULL,
    total_articles INT UNSIGNED NOT NULL,
    status ENUM('active', 'archived', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document Sections (títulos, capítulos, secciones)
CREATE TABLE document_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    section_type ENUM('title', 'chapter', 'section') NOT NULL,
    name VARCHAR(255) NOT NULL,
    order_index INT UNSIGNED NOT NULL,
    parent_section_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES legal_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_section_id) REFERENCES document_sections(id) ON DELETE SET NULL,
    INDEX idx_document_type (document_id, section_type),
    INDEX idx_parent (parent_section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles (contenido principal)
CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NULL,
    article_number INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    title VARCHAR(255) NULL,
    chapter VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM('active', 'modified', 'repealed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES legal_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES document_sections(id) ON DELETE SET NULL,
    UNIQUE KEY unique_article (document_id, article_number),
    INDEX idx_number (article_number),
    INDEX idx_status (status),
    FULLTEXT KEY ft_content (content, title, chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Concordances (referencias cruzadas)
CREATE TABLE concordances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    referenced_law VARCHAR(255) NOT NULL,
    referenced_articles JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_article (article_id),
    INDEX idx_law (referenced_law)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Article History (audit trail)
CREATE TABLE article_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    content_before TEXT NOT NULL,
    content_after TEXT NOT NULL,
    modified_by VARCHAR(100) NOT NULL,
    modification_reason VARCHAR(255) NULL,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_article_date (article_id, modified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial document
INSERT INTO legal_documents (name, document_type, year, last_modified, total_articles, status)
VALUES ('Constitución de la República del Ecuador', 'constitution', 2008, '2021-01-25', 467, 'active');
