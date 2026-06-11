-- ════════════════════════════════════════════════════════════════════════════
-- SCHÉMA DE BASE DE DONNÉES - BANQUE EN LIGNE
-- PostgreSQL 14+
-- ════════════════════════════════════════════════════════════════════════════

-- Activer les extensions nécessaires
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ════════════════════════════════════════════════════════════════════════════
-- TABLE: BANQUE
-- ════════════════════════════════════════════════════════════════════════════
CREATE TABLE banque (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    statut VARCHAR(20) DEFAULT 'actif' NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    role VARCHAR(50) DEFAULT 'ROLE_BANQUE' NOT NULL,
    
    -- Contraintes
    CONSTRAINT check_statut_banque CHECK (statut IN ('actif', 'inactif')),
    CONSTRAINT check_role_banque CHECK (role = 'ROLE_BANQUE')
);

-- Index
CREATE INDEX idx_banque_email ON banque(email);
CREATE INDEX idx_banque_statut ON banque(statut);

-- ════════════════════════════════════════════════════════════════════════════
-- TABLE: CLIENT
-- ════════════════════════════════════════════════════════════════════════════
CREATE TABLE client (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    role VARCHAR(50) DEFAULT 'ROLE_CLIENT' NOT NULL,
    statut VARCHAR(20) DEFAULT 'actif' NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    banque_id INT,
    
    -- Clés étrangères
    CONSTRAINT fk_client_banque FOREIGN KEY (banque_id) 
        REFERENCES banque(id) ON DELETE SET NULL,
    
    -- Contraintes
    CONSTRAINT check_role_client CHECK (role IN ('ROLE_CLIENT', 'ROLE_ADMIN')),
    CONSTRAINT check_statut_client CHECK (statut IN ('actif', 'inactif')),
    CONSTRAINT check_admin_no_banque CHECK (
        (role = 'ROLE_ADMIN' AND banque_id IS NULL) OR 
        (role = 'ROLE_CLIENT' AND banque_id IS NOT NULL)
    )
);

-- Index
CREATE INDEX idx_client_email ON client(email);
CREATE INDEX idx_client_banque_id ON client(banque_id);
CREATE INDEX idx_client_statut ON client(statut);
CREATE INDEX idx_client_role ON client(role);

-- ════════════════════════════════════════════════════════════════════════════
-- TABLE: COMPTE
-- ════════════════════════════════════════════════════════════════════════════
CREATE TABLE compte (
    id SERIAL PRIMARY KEY,
    numero_compte VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) DEFAULT 'courant' NOT NULL,
    solde DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    devise VARCHAR(3) DEFAULT 'EUR' NOT NULL,
    statut VARCHAR(20) DEFAULT 'actif' NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    client_id INT NOT NULL,
    
    -- Clés étrangères
    CONSTRAINT fk_compte_client FOREIGN KEY (client_id) 
        REFERENCES client(id) ON DELETE CASCADE,
    
    -- Contraintes
    CONSTRAINT check_type_compte CHECK (type IN ('courant', 'épargne', 'titre')),
    CONSTRAINT check_solde CHECK (solde >= 0),
    CONSTRAINT check_statut_compte CHECK (statut IN ('actif', 'inactif')),
    CONSTRAINT check_devise CHECK (devise ~ '^[A-Z]{3}$')
);

-- Index
CREATE INDEX idx_compte_numero ON compte(numero_compte);
CREATE INDEX idx_compte_client_id ON compte(client_id);
CREATE INDEX idx_compte_statut ON compte(statut);

-- ════════════════════════════════════════════════════════════════════════════
-- TABLE: TRANSACTION
-- ════════════════════════════════════════════════════════════════════════════
CREATE TABLE transaction (
    id SERIAL PRIMARY KEY,
    montant DECIMAL(15, 2) NOT NULL,
    type VARCHAR(20) NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    frais DECIMAL(15, 2) DEFAULT 0.00,
    date_transaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    compte_source_id INT,
    compte_destination_id INT,
    statut VARCHAR(20) NOT NULL,
    
    -- Clés étrangères
    CONSTRAINT fk_transaction_source FOREIGN KEY (compte_source_id) 
        REFERENCES compte(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_destination FOREIGN KEY (compte_destination_id) 
        REFERENCES compte(id) ON DELETE SET NULL,
    
    -- Contraintes
    CONSTRAINT check_montant CHECK (montant > 0),
    CONSTRAINT check_type_transaction CHECK (type IN ('débit', 'crédit')),
    CONSTRAINT check_statut_transaction CHECK (statut IN ('succès', 'échoué')),
    CONSTRAINT check_frais CHECK (frais >= 0)
);

-- Index
CREATE INDEX idx_transaction_date ON transaction(date_transaction);
CREATE INDEX idx_transaction_compte_source ON transaction(compte_source_id);
CREATE INDEX idx_transaction_compte_destination ON transaction(compte_destination_id);
CREATE INDEX idx_transaction_statut ON transaction(statut);

-- ════════════════════════════════════════════════════════════════════════════
-- VUES UTILES (optionnel)
-- ════════════════════════════════════════════════════════════════════════════

-- Vue: Solde total par client
CREATE VIEW v_solde_clients AS
SELECT 
    c.id,
    c.nom,
    c.prenom,
    c.email,
    b.nom AS banque,
    COUNT(co.id) AS nb_comptes,
    SUM(co.solde) AS solde_total
FROM client c
LEFT JOIN banque b ON c.banque_id = b.id
LEFT JOIN compte co ON c.id = co.client_id AND co.statut = 'actif'
WHERE c.role = 'ROLE_CLIENT'
GROUP BY c.id, c.nom, c.prenom, c.email, b.nom;

-- Vue: Statistiques par banque
CREATE VIEW v_stats_banques AS
SELECT 
    b.id,
    b.nom,
    COUNT(DISTINCT c.id) AS nb_clients,
    COUNT(DISTINCT co.id) AS nb_comptes,
    SUM(co.solde) AS solde_total_gere
FROM banque b
LEFT JOIN client c ON b.id = c.banque_id AND c.role = 'ROLE_CLIENT'
LEFT JOIN compte co ON c.id = co.client_id AND co.statut = 'actif'
WHERE b.statut = 'actif'
GROUP BY b.id, b.nom;

-- Vue: Historique des transactions
CREATE VIEW v_historique_transactions AS
SELECT 
    t.id,
    t.date_transaction,
    t.montant,
    t.type,
    t.libelle,
    t.frais,
    t.statut,
    cs.numero_compte AS compte_source,
    cd.numero_compte AS compte_destination,
    cli_source.nom AS client_source,
    cli_dest.nom AS client_destination
FROM transaction t
LEFT JOIN compte cs ON t.compte_source_id = cs.id
LEFT JOIN compte cd ON t.compte_destination_id = cd.id
LEFT JOIN client cli_source ON cs.client_id = cli_source.id
LEFT JOIN client cli_dest ON cd.client_id = cli_dest.id
ORDER BY t.date_transaction DESC;

-- ════════════════════════════════════════════════════════════════════════════
-- DONNÉES DE TEST (OPTIONNEL)
-- ════════════════════════════════════════════════════════════════════════════

-- Insérer une banque test
INSERT INTO banque (nom, email, mot_de_passe, telephone, statut, role)
VALUES ('Banque Test', 'test@banque.fr', '$2y$13$...', '+33123456789', 'actif', 'ROLE_BANQUE');

-- Insérer un client admin
INSERT INTO client (nom, prenom, email, mot_de_passe, role, statut)
VALUES ('Admin', 'System', 'admin@system.fr', '$2y$13$...', 'ROLE_ADMIN', 'actif');

-- Insérer un client test
INSERT INTO client (nom, prenom, email, mot_de_passe, telephone, role, statut, banque_id)
VALUES ('Dupont', 'Jean', 'jean.dupont@email.fr', '$2y$13$...', '+33712345678', 'ROLE_CLIENT', 'actif', 1);

-- Insérer un compte test
INSERT INTO compte (numero_compte, type, solde, devise, statut, client_id)
VALUES ('FR1234567890123456789012345', 'courant', 1000.00, 'EUR', 'actif', 1);

-- ════════════════════════════════════════════════════════════════════════════
-- PROCÉDURES STOCKÉES (OPTIONNEL)
-- ════════════════════════════════════════════════════════════════════════════

-- Procédure: Effectuer un virement
CREATE OR REPLACE FUNCTION effectuer_virement(
    p_montant DECIMAL,
    p_compte_source INT,
    p_compte_destination INT,
    p_libelle VARCHAR
) RETURNS TABLE (success BOOLEAN, message VARCHAR) AS $$
DECLARE
    v_solde_source DECIMAL;
    v_statut_source VARCHAR;
    v_statut_destination VARCHAR;
BEGIN
    -- Vérifier que les comptes existent
    SELECT solde, statut INTO v_solde_source, v_statut_source
    FROM compte WHERE id = p_compte_source;
    
    IF v_solde_source IS NULL THEN
        RETURN QUERY SELECT false, 'Compte source introuvable'::VARCHAR;
        RETURN;
    END IF;
    
    SELECT statut INTO v_statut_destination
    FROM compte WHERE id = p_compte_destination;
    
    IF v_statut_destination IS NULL THEN
        RETURN QUERY SELECT false, 'Compte destination introuvable'::VARCHAR;
        RETURN;
    END IF;
    
    -- Vérifier les règles métier
    IF v_statut_source = 'inactif' THEN
        RETURN QUERY SELECT false, 'Le compte source est désactivé'::VARCHAR;
        RETURN;
    END IF;
    
    IF v_statut_destination = 'inactif' THEN
        RETURN QUERY SELECT false, 'Le compte destination est désactivé'::VARCHAR;
        RETURN;
    END IF;
    
    IF v_solde_source < p_montant THEN
        -- Enregistrer l'échec
        INSERT INTO transaction (montant, type, libelle, date_transaction, compte_source_id, statut)
        VALUES (p_montant, 'débit', p_libelle, CURRENT_TIMESTAMP, p_compte_source, 'échoué');
        
        RETURN QUERY SELECT false, 'Solde insuffisant'::VARCHAR;
        RETURN;
    END IF;
    
    -- Effectuer le virement (transaction atomique)
    BEGIN
        -- Débiter le compte source
        UPDATE compte SET solde = solde - p_montant WHERE id = p_compte_source;
        
        -- Créditer le compte destination
        UPDATE compte SET solde = solde + p_montant WHERE id = p_compte_destination;
        
        -- Créer la transaction débit
        INSERT INTO transaction (montant, type, libelle, date_transaction, compte_source_id, compte_destination_id, statut)
        VALUES (p_montant, 'débit', p_libelle, CURRENT_TIMESTAMP, p_compte_source, p_compte_destination, 'succès');
        
        -- Créer la transaction crédit
        INSERT INTO transaction (montant, type, libelle, date_transaction, compte_source_id, compte_destination_id, statut)
        VALUES (p_montant, 'crédit', p_libelle, CURRENT_TIMESTAMP, p_compte_source, p_compte_destination, 'succès');
        
        RETURN QUERY SELECT true, 'Virement effectué avec succès'::VARCHAR;
    EXCEPTION WHEN OTHERS THEN
        RETURN QUERY SELECT false, 'Erreur lors du virement'::VARCHAR;
    END;
END;
$$ LANGUAGE plpgsql;

-- ════════════════════════════════════════════════════════════════════════════
-- FIN DU SCHÉMA
-- ════════════════════════════════════════════════════════════════════════════
