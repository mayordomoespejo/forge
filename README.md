# Forge

AI-powered content analyzer — upload text, images, or documents and get structured insights plus an interactive chat.

---

## Features

- Analyze plain text with sentiment scoring, entity recognition, and key phrase extraction
- Analyze images with visual description, object detection, and color identification
- Extract structured data from PDF documents using Azure Document Intelligence
- Chat with an AI assistant that has full context of your analyzed content
- Supports drag-and-drop file uploads
- Translate analyzed content to 11 languages using Azure Translator

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3 |
| HTTP client | Guzzle 7 |
| Generative AI | GitHub Models — gpt-4o-mini (Azure AI Inference) |
| Language analysis | Azure AI Language |
| Document extraction | Azure Document Intelligence |
| Configuration | vlucas/phpdotenv |
| Frontend | Vanilla HTML, CSS, JavaScript |

---

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- A GitHub Personal Access Token (for GitHub Models)
- An Azure account with the following resources provisioned:
  - Azure AI Language (F0 free tier is sufficient)
  - Azure Document Intelligence (F0 free tier is sufficient)

### Installation

```bash
git clone https://github.com/miguelmayordomoespejo/forge
cd forge
composer install
cp .env.example .env
```

Fill in your credentials in `.env`, then start the local server:

```bash
php -S localhost:8000 index.php
```

Open [http://localhost:8000](http://localhost:8000) in your browser.

---

## Usage

1. Open the app and either upload a file (PDF or image) or paste plain text
2. Click **Analyze** — the app processes your content with Azure AI services
3. Review the generated insights in the results panel
4. Use the chat interface to ask questions about your content

---

## Configuration

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | GitHub Personal Access Token with `models:read` permission |
| `AZURE_LANGUAGE_ENDPOINT` | Azure AI Language resource endpoint URL |
| `AZURE_LANGUAGE_KEY` | Azure AI Language API key |
| `AZURE_DOCUMENT_ENDPOINT` | Azure Document Intelligence resource endpoint URL |
| `AZURE_DOCUMENT_KEY` | Azure Document Intelligence API key |
| `AZURE_TRANSLATOR_KEY` | Azure Translator resource API key |
| `AZURE_TRANSLATOR_REGION` | Azure region for the Translator resource (e.g. `eastus`) |

---

## License

MIT
