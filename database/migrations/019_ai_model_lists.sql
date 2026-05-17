-- Listes de modèles IA configurables (une valeur par ligne)
INSERT IGNORE INTO site_config (cle, valeur) VALUES
('ai_gemini_models',
'gemini-2.5-flash
gemini-2.5-pro
gemini-2.0-flash
gemini-1.5-flash
gemini-1.5-pro'),
('ai_imagen_models',
'gemini-2.5-flash-image
imagen-3.0-generate-002
imagen-3.0-fast-generate-001');
