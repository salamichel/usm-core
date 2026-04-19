import sharp from 'sharp'
import { promises as fs } from 'fs'
import path from 'path'

export interface ImageOptimizationOptions {
  width?: number
  height?: number
  quality?: number
  format?: 'jpeg' | 'png' | 'webp'
}

export async function optimizeImage(
  inputPath: string,
  outputPath: string,
  options: ImageOptimizationOptions = {}
): Promise<void> {
  const {
    width = 1920,
    height = 1080,
    quality = 80,
    format = 'webp',
  } = options

  let pipeline = sharp(inputPath)
    .resize(width, height, {
      fit: 'inside',
      withoutEnlargement: true,
    })

  if (format === 'jpeg') {
    pipeline = pipeline.jpeg({ quality })
  } else if (format === 'webp') {
    pipeline = pipeline.webp({ quality })
  } else if (format === 'png') {
    pipeline = pipeline.png({ quality })
  }

  // Ensure output directory exists
  const outputDir = path.dirname(outputPath)
  await fs.mkdir(outputDir, { recursive: true })

  await pipeline.toFile(outputPath)
}

export async function generateThumbnail(
  inputPath: string,
  outputPath: string,
  size: number = 200
): Promise<void> {
  await optimizeImage(inputPath, outputPath, {
    width: size,
    height: size,
    quality: 85,
    format: 'webp',
  })
}

export async function generateMultipleThumbnails(
  inputPath: string,
  outputDir: string,
  sizes: number[] = [200, 400, 800]
): Promise<string[]> {
  const results: string[] = []

  for (const size of sizes) {
    const filename = `thumb-${size}.webp`
    const outputPath = path.join(outputDir, filename)
    await generateThumbnail(inputPath, outputPath, size)
    results.push(outputPath)
  }

  return results
}
