CREATE TABLE IF NOT EXISTS ai_image_contexts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  style_prompt TEXT NOT NULL,
  gemini_model VARCHAR(100) NOT NULL DEFAULT 'gemini-2.0-flash',
  imagen_model VARCHAR(100) NOT NULL DEFAULT 'imagen-3.0-generate-002',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO ai_image_contexts (id, name, style_prompt, gemini_model, imagen_model, is_default) VALUES
(1, 'Volley Sport', 'Professional volleyball sport photography, dynamic action, bright court lighting, energetic atmosphere, vibrant colors', 'gemini-2.0-flash', 'imagen-3.0-generate-002', 1),
(2, 'Article Blog', 'Modern blog header illustration, clean minimalist design, abstract geometric shapes, soft colors, editorial style', 'gemini-2.0-flash', 'imagen-3.0-generate-002', 0);
