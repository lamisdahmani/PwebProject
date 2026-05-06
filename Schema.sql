-- ============================================================
--  SCHEMA MySQL — Plateforme Tirage au Sort Hadj 2026
--  Encodage : UTF-8 | Moteur : InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';      -- heure algérienne (UTC+1)

-- ============================================================
--  CRÉATION DE LA BASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS hadj_tirage
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hadj_tirage;

-- ============================================================
--  TABLE : wilayas  (référentiel, 58 wilayas)
-- ============================================================
CREATE TABLE IF NOT EXISTS wilayas (
  id_wilaya   TINYINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nom_wilaya  VARCHAR(60)       NOT NULL,
  PRIMARY KEY (id_wilaya)
) ENGINE=InnoDB;

INSERT INTO wilayas (nom_wilaya) VALUES
('Adrar'),('Chlef'),('Laghouat'),('Oum El Bouaghi'),('Batna'),
('Béjaïa'),('Biskra'),('Béchar'),('Blida'),('Bouira'),
('Tamanrasset'),('Tébessa'),('Tlemcen'),('Tiaret'),('Tizi Ouzou'),
('Alger'),('Djelfa'),('Jijel'),('Sétif'),('Saïda'),
('Skikda'),('Sidi Bel Abbès'),('Annaba'),('Guelma'),('Constantine'),
('Médéa'),('Mostaganem'),("M'Sila"),('Mascara'),('Ouargla'),
('Oran'),('El Bayadh'),('Illizi'),('Bordj Bou Arréridj'),('Boumerdès'),
('El Tarf'),('Tindouf'),('Tissemsilt'),('El Oued'),('Khenchela'),
('Souk Ahras'),('Tipaza'),('Mila'),('Aïn Defla'),('Naâma'),
('Aïn Témouchent'),('Ghardaïa'),('Relizane'),('Timinoun'),
('Bordj Badji Mokhtar'),('Ouled Djellal'),('Beni Abbès'),
('Aïn Salah'),('Aïn Guezzam'),('Touggourt'),('Djanet'),
("M'Ghair"),('Meniaa');

-- ============================================================
--  TABLE : utilisateurs
--  etat_compte : 1=actif  2=bloqué  3=en_attente  4=supprimé
--  role        : 1=admin  2=utilisateur
-- ============================================================
CREATE TABLE IF NOT EXISTS utilisateurs (
  id_utilisateur  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  nin             CHAR(18)          NOT NULL,
  nom             VARCHAR(60)       NOT NULL,
  prenom          VARCHAR(60)       NOT NULL,
  prenom_pere     VARCHAR(60)       NOT NULL,
  prenom_grandpere VARCHAR(60)      NOT NULL,
  nom_mere        VARCHAR(100)      NOT NULL,
  date_naissance  DATE              NOT NULL,
  email           VARCHAR(150)      NOT NULL,
  telephone       VARCHAR(15)       NOT NULL,
  id_wilaya       TINYINT UNSIGNED  NOT NULL,
  mot_de_passe    CHAR(64)          NOT NULL,   -- SHA-256 hex
  role            TINYINT           NOT NULL DEFAULT 2,
  etat_compte     TINYINT           NOT NULL DEFAULT 3,
  date_creation   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id_utilisateur),
  UNIQUE KEY uq_nin   (nin),
  UNIQUE KEY uq_email (email),
  FOREIGN KEY (id_wilaya) REFERENCES wilayas(id_wilaya)
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE : tirages
--  etat : 0=non_configure  1=configure  2=inscriptions_ouvertes
--         3=inscriptions_fermees  4=effectue
-- ============================================================
CREATE TABLE IF NOT EXISTS tirages (
  id_tirage           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  annee               YEAR          NOT NULL,
  nb_gagnants         INT UNSIGNED  NOT NULL DEFAULT 0,
  date_tirage         DATE              NULL,
  date_ouverture_inscr DATE             NULL,
  date_cloture_inscr  DATE              NULL,
  etat                TINYINT       NOT NULL DEFAULT 0,
  date_lancement      DATETIME          NULL,

  PRIMARY KEY (id_tirage),
  UNIQUE KEY uq_annee (annee)
) ENGINE=InnoDB;

-- Tirage 2025 (finalisé — données historiques)
INSERT INTO tirages (annee, nb_gagnants, date_tirage,
                     date_ouverture_inscr, date_cloture_inscr,
                     etat, date_lancement)
VALUES (2025, 500, '2025-05-15', '2025-04-01', '2025-04-30', 4, '2025-05-15 10:00:00');

-- Tirage 2026 (en cours de paramétrage)
INSERT INTO tirages (annee, nb_gagnants, date_tirage,
                     date_ouverture_inscr, date_cloture_inscr, etat)
VALUES (2026, 500, '2026-05-15', '2026-04-01', '2026-05-10', 2);

-- ============================================================
--  TABLE : inscriptions
--  Lien entre un utilisateur et un tirage.
--  nb_participations : fois où l'utilisateur apparaît dans l'urne
--                      (1 + nombre de tirages perdus consécutifs)
-- ============================================================
CREATE TABLE IF NOT EXISTS inscriptions (
  id_inscription   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_utilisateur   INT UNSIGNED  NOT NULL,
  id_tirage        INT UNSIGNED  NOT NULL,
  date_inscription DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nb_participations TINYINT UNSIGNED NOT NULL DEFAULT 1,

  PRIMARY KEY (id_inscription),
  UNIQUE KEY uq_user_tirage (id_utilisateur, id_tirage),
  FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur)
    ON DELETE CASCADE,
  FOREIGN KEY (id_tirage) REFERENCES tirages(id_tirage)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE : resultats
--  Stocke le résultat de chaque inscription après le tirage.
--  gagnant : 1=gagnant  0=non_gagnant
-- ============================================================
CREATE TABLE IF NOT EXISTS resultats (
  id_resultat    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_inscription INT UNSIGNED  NOT NULL,
  gagnant        TINYINT(1)    NOT NULL DEFAULT 0,
  date_resultat  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id_resultat),
  UNIQUE KEY uq_inscription (id_inscription),
  FOREIGN KEY (id_inscription) REFERENCES inscriptions(id_inscription)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE : notifications
--  type : 'ouverture_inscr' | 'date_tirage' | 'resultat_gagnant'
--         | 'resultat_non_gagnant'
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id_notif       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_utilisateur INT UNSIGNED  NOT NULL,
  type_notif     VARCHAR(30)   NOT NULL,
  message        VARCHAR(255)  NOT NULL,
  lu             TINYINT(1)    NOT NULL DEFAULT 0,
  date_notif     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id_notif),
  FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  DONNÉES DE TEST — Comptes utilisateurs
--  Mot de passe de tous les utilisateurs test : "User1234!"
--  Mot de passe admin : "Admin123!"
--  (SHA-256 pré-calculé)
-- ============================================================

-- Admin
INSERT INTO utilisateurs
  (nin, nom, prenom, prenom_pere, prenom_grandpere, nom_mere,
   date_naissance, email, telephone, id_wilaya, mot_de_passe, role, etat_compte)
VALUES
  ('111111111111111111','Admin','Principal','Mohamed','Ahmed','Khelifi Fatima',
   '1980-01-01','admin@hadj.dz','0555000001',16,
   SHA2('Admin123!',256), 1, 1);

-- Utilisateurs actifs (etat=1)
INSERT INTO utilisateurs
  (nin, nom, prenom, prenom_pere, prenom_grandpere, nom_mere,
   date_naissance, email, telephone, id_wilaya, mot_de_passe, role, etat_compte)
VALUES
  ('222222222222222222','Dahmani','Lamis','Mohamed','Ahmed','Khelifi Fatima',
   '1995-03-12','lamis@gmail.com','0555364788',16,
   SHA2('User1234!',256), 2, 1),

  ('632547891012345678','Zenati','Mourad','Karim','Ali','Benali Khadija',
   '1985-01-30','mourad.z@gmail.com','0661234567',6,
   SHA2('User1234!',256), 2, 1);

-- Utilisateur bloqué (etat=2)
INSERT INTO utilisateurs
  (nin, nom, prenom, prenom_pere, prenom_grandpere, nom_mere,
   date_naissance, email, telephone, id_wilaya, mot_de_passe, role, etat_compte)
VALUES
  ('333333333333333333','Rezki','Maria','Rachid','Omar','Mansouri Nadia',
   '1988-11-22','bloque@gmail.com','0771234567',9,
   SHA2('User1234!',256), 2, 2);

-- Utilisateurs en attente (etat=3)
INSERT INTO utilisateurs
  (nin, nom, prenom, prenom_pere, prenom_grandpere, nom_mere,
   date_naissance, email, telephone, id_wilaya, mot_de_passe, role, etat_compte)
VALUES
  ('444444444444444444','Torkman','Reda','Samir','Hocine','Torkman Amina',
   '1992-06-18','attente@gmail.com','0551234567',25,
   SHA2('User1234!',256), 2, 3),

  ('741258963012345678','Mekki','Samir','Yacine','Abdelkader','Mekki Zohra',
   '1993-09-14','samir.m@email.dz','0561234567',31,
   SHA2('User1234!',256), 2, 3),

  ('963852741012345678','Zouaoui','Nadia','Fouad','Mabrouk','Zouaoui Selma',
   '1997-04-07','nadia.z@gmail.com','0771234568',19,
   SHA2('User1234!',256), 2, 3);

-- ============================================================
--  DONNÉES DE TEST — Inscriptions et résultats historiques (2025)
-- ============================================================

-- Dahmani Lamis inscrite au tirage 2025 (non gagnante)
INSERT INTO inscriptions (id_utilisateur, id_tirage, nb_participations)
  SELECT u.id_utilisateur, t.id_tirage, 1
  FROM utilisateurs u, tirages t
  WHERE u.nin='222222222222222222' AND t.annee=2025;

INSERT INTO resultats (id_inscription, gagnant)
  SELECT i.id_inscription, 0
  FROM inscriptions i
  JOIN utilisateurs u ON u.id_utilisateur=i.id_utilisateur
  JOIN tirages t ON t.id_tirage=i.id_tirage
  WHERE u.nin='222222222222222222' AND t.annee=2025;

-- Zenati Mourad inscrit au tirage 2025 (gagnant)
INSERT INTO inscriptions (id_utilisateur, id_tirage, nb_participations)
  SELECT u.id_utilisateur, t.id_tirage, 3
  FROM utilisateurs u, tirages t
  WHERE u.nin='632547891012345678' AND t.annee=2025;

INSERT INTO resultats (id_inscription, gagnant)
  SELECT i.id_inscription, 1
  FROM inscriptions i
  JOIN utilisateurs u ON u.id_utilisateur=i.id_utilisateur
  JOIN tirages t ON t.id_tirage=i.id_tirage
  WHERE u.nin='632547891012345678' AND t.annee=2025;

-- ============================================================
--  VUE UTILITAIRE : résultats publics du dernier tirage effectué
-- ============================================================
CREATE OR REPLACE VIEW v_resultats_publics AS
SELECT
  u.nin,
  CONCAT(u.nom,' ',u.prenom)   AS nom_complet,
  w.nom_wilaya                 AS wilaya,
  t.annee,
  r.gagnant
FROM resultats r
JOIN inscriptions i ON i.id_inscription = r.id_inscription
JOIN utilisateurs u ON u.id_utilisateur = i.id_utilisateur
JOIN wilayas      w ON w.id_wilaya      = u.id_wilaya
JOIN tirages      t ON t.id_tirage      = i.id_tirage
ORDER BY t.annee DESC, r.gagnant DESC;

-- ============================================================
--  VUE UTILITAIRE : nb participations sans gain par utilisateur
--  Sert au calcul du bonus (pondération urne)
-- ============================================================
CREATE OR REPLACE VIEW v_bonus_utilisateur AS
SELECT
  u.id_utilisateur,
  COALESCE(SUM(CASE WHEN r.gagnant=0 THEN 1 ELSE 0 END), 0) AS nb_pertes,
  -- Il faut 1 + nb_pertes apparitions dans l'urne
  COALESCE(SUM(CASE WHEN r.gagnant=0 THEN 1 ELSE 0 END), 0) + 1 AS poids_urne
FROM utilisateurs u
LEFT JOIN inscriptions i ON i.id_utilisateur = u.id_utilisateur
LEFT JOIN resultats    r ON r.id_inscription = i.id_inscription
GROUP BY u.id_utilisateur;