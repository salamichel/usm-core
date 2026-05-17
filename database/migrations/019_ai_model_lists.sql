-- Listes de modèles IA configurables (une valeur par ligne)
INSERT IGNORE INTO site_config (cle, valeur) VALUES
('ai_gemini_models',
'gemini-3.1-pro-preview
gemini-3-flash-preview
gemini-3.1-flash-lite
gemini-3.1-flash-live-preview
gemini-2.5-flash
gemini-2.5-pro
gemini-2.0-flash'),
('ai_imagen_models',
'gemini-2.5-flash-image
gemini-3.1-flash-image-preview
gemini-3-pro-image-preview');
