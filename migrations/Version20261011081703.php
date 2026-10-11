<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011081703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE appointment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, all_day BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, owner_id INTEGER NOT NULL, booking_id INTEGER DEFAULT NULL, CONSTRAINT FK_FE38F8447E3C61F9 FOREIGN KEY (owner_id) REFERENCES person (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE38F8443301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FE38F84455A0507C2F4A8AA7 ON appointment (starts_at, ends_at)');
        $this->addSql('CREATE INDEX IDX_FE38F8447E3C61F9 ON appointment (owner_id)');
        $this->addSql('CREATE INDEX IDX_FE38F8443301C60 ON appointment (booking_id)');
        $this->addSql('CREATE TABLE appointment_participant (appointment_id INTEGER NOT NULL, person_id INTEGER NOT NULL, PRIMARY KEY (appointment_id, person_id), CONSTRAINT FK_AC57098DE5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC57098D217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AC57098DE5B533F9 ON appointment_participant (appointment_id)');
        $this->addSql('CREATE INDEX IDX_AC57098D217BBB47 ON appointment_participant (person_id)');
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, all_day BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, room_id INTEGER NOT NULL, organizer_id INTEGER NOT NULL, CONSTRAINT FK_E00CEDDE54177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E00CEDDE876C4DDA FOREIGN KEY (organizer_id) REFERENCES person (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE55A0507C2F4A8AA7 ON booking (starts_at, ends_at)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE54177093 ON booking (room_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE876C4DDA ON booking (organizer_id)');
        $this->addSql('CREATE TABLE building (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, address VARCHAR(255) DEFAULT NULL, featured BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E16F61D45E237E06 ON building (name)');
        $this->addSql('CREATE TABLE person (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, team VARCHAR(60) NOT NULL, color VARCHAR(7) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_34DCD176E7927C74 ON person (email)');
        $this->addSql('CREATE TABLE room (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, kind VARCHAR(255) NOT NULL, capacity INTEGER NOT NULL, position INTEGER NOT NULL, building_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, CONSTRAINT FK_729F519B4D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_729F519B727ACA70 FOREIGN KEY (parent_id) REFERENCES room (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_729F519B3BC4BCD9 ON room (kind)');
        $this->addSql('CREATE INDEX IDX_729F519B4D2A7E12 ON room (building_id)');
        $this->addSql('CREATE INDEX IDX_729F519B727ACA70 ON room (parent_id)');
        $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tenant_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, value CLOB NOT NULL, type VARCHAR(20) DEFAULT \'string\' NOT NULL, active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX idx_setting_tenant_active ON setting (tenant_id, active)');
        $this->addSql('CREATE UNIQUE INDEX uniq_setting_tenant_name ON setting (tenant_id, name)');
        $this->addSql('CREATE TABLE setting_scoped (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tenant_id INTEGER NOT NULL, namespace VARCHAR(100) NOT NULL, level VARCHAR(50) NOT NULL, scope_id VARCHAR(191) DEFAULT \'\' NOT NULL, setting_key VARCHAR(100) NOT NULL, value CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_setting_scoped ON setting_scoped (tenant_id, namespace, level, scope_id, setting_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE appointment');
        $this->addSql('DROP TABLE appointment_participant');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE building');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE setting_scoped');
    }
}
