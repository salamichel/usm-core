-- Migration 005: Add meta_description columns for AI-generated SEO content
-- Adds optional meta_description field to both posts and pages tables

ALTER TABLE posts
ADD COLUMN meta_description VARCHAR(160) NULL
AFTER excerpt;

ALTER TABLE pages
ADD COLUMN meta_description VARCHAR(160) NULL
AFTER content;
