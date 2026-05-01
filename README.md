# DolanJogja - Jogja Smart Travel Assistant 🗺️✨

**DolanJogja** adalah platform panduan wisata berbasis kecerdasan buatan (AI) yang dirancang khusus untuk menjawab persoalan struktural pariwisata di Daerah Istimewa Yogyakarta, seperti *overtourism* di destinasi populer dan ketidakefisienan rute perjalanan (zig-zag).

## 📌 Informasi Produk
- **Nama Produk:** DolanJogja - Jogja Smart Travel Assistant
- **Platform:** Web App & Mobile App - Web Based (Progressive Web Apps / PWA)
- **Target Pengguna:** Wisatawan domestik dan mancanegara yang berkunjung ke Yogyakarta.

## 📖 Latar Belakang & Ringkasan Eksekutif
Yogyakarta merupakan salah satu destinasi wisata terpopuler di Indonesia dengan jutaan perjalanan wisata setiap musim liburan. Sayangnya, kunjungan ini kerap tidak terencana dengan baik karena hanya mengandalkan konten viral media sosial tanpa panduan rute dan informasi kepadatan yang memadai.

Hal ini memunculkan beberapa masalah utama:
1. **Overtourism & Ketimpangan Distribusi:** Lebih dari 62% kunjungan terpusat di Sleman dan Kota Yogyakarta. Hidden gems dan pelaku wisata lokal di wilayah lain kurang mendapat porsi.
2. **Kemacetan & Rute Zig-zag:** Perjalanan impulsif membuat rute antar-destinasi sering zig-zag, menyebabkan kemacetan dan inefisiensi waktu liburan.

DolanJogja menyelesaikan masalah ini dengan **menyusun itinerary otomatis dari AI** yang logis, terklaster, dan merata untuk mendukung ekosistem pariwisata yang sehat, inklusif, dan nyaman.

## ✨ Fitur Utama
- 🤖 **Itinerary Generator AI:** Menyusun jadwal harian otomatis berdasarkan preferensi, budget, dan durasi. Bebas rute zig-zag!
- 💬 **Virtual Tour Guide Chatbot:** Asisten percakapan AI 24/7 (Bahasa Indonesia & Inggris) untuk tanya-jawab seputar wisata Jogja secara real-time.
- 🎭 **Mood-Based Recommendation:** Memberi rekomendasi presisi dari prompt *natural language* (contoh: "wisata tenang untuk anak usia 5 tahun").
- 💰 **Dynamic Budget Estimator:** Menghitung otomatis perkiraan total biaya transportasi, tiket, dan makan.
- 💎 **Hidden Gems Distributor:** Meratakan arus wisata dengan menyodorkan destinasi alternatif yang tidak terlalu padat.
- 🚍 **Transport Card Multimodal:** Menampilkan opsi lengkap kendaraan (Trans Jogja, Gojek/Grab, Becak, Andong, KRL) beserta biayanya.
- 🌦️ **Weather-Aware Planning:** Adaptasi cerdas ke destinasi *indoor* bila cuaca diprediksi hujan.
- 📷 **Destinasi Recognition via Foto:** Mengidentifikasi nama tempat dan detailnya hanya lewat foto.

## 🗄️ Database Konten (Terkurasi)
- **Destinasi:** Alam, Museum, Kuliner, Rekreasi, Sejarah
- **Event:** Konser Musik, Tradisi (Tari, Wayang, dll), Pameran, Festival
- **Akomodasi:** Hotel, Guest House, Homestay, Villa
- **Transportasi Umum:** Trans Jogja, KRL, Damri, Becak, Andong, Ojek Online

## 🛠️ Teknologi yang Digunakan
DolanJogja didukung oleh infrastruktur modern dan tangguh, terutama mengandalkan layanan **Microsoft Azure**:
- **Azure OpenAI Service (GPT-4o)**: Otak di balik itinerary generator, chatbot, & budget estimator.
- **Azure Maps**: Optimalisasi rute (*multi-stop*) dan *parking spot finder*.
- **Azure Computer Vision**: Identifikasi foto destinasi & pemantauan kepadatan massa (Roadmap).
- **Azure Cognitive Services**: Penerjemahan instan (Multilingual support).
- **Azure AI Search (RAG)**: Mengaitkan database lokal (destinasi, kuliner) secara akurat ke AI agar bebas halusinasi.
- **OpenWeather API**: Perencanaan jadwal sadar cuaca.
- **CodeIgniter 4**: REST API, pengembangan Web App, dan panel Admin.

## 🗺️ Roadmap Pengembangan
| Fase | Periode | Fokus Fitur | Status / Output |
| :---: | :---: | :--- | :--- |
| **MVP** | Apr – Jun 2026 | Itinerary AI, Chatbot, Mood Rec, Budget, Maps, Trans Jogja | Prototype |
| **V1** | Jul – Sep 2026 | Integrasi CCTV, Crowd Prediction, Weather Planner, Booking OTA, KRL API | Beta Product |
| **V2** | Des 2026 | Sistem Parkir Pemda, Dashboard Pemda, Integrasi API Dispar DIY | Go-to-Market |
| **V3** | Q2 2027 | Dashboard analitik untuk Dispar (Sebaran wisata, kepadatan) | Laporan Data Eksekutif |

## 👥 Tim & Peran
- 👨‍💻 **Ahmad Ridlo Fadlli Robbi** – *Full Stack Developer*
  (Mengelola arsitektur teknis, integrasi API Azure, dan pengembangan web/mobile)
- 👩‍💼 **Ani Wahyuni** – *QA Engineer & Jogja Local Expert*
  (Quality Assurance produk dan kurasi konten destinasi lokal "Hidden Gems" Jogja)

## 🔗 Tautan Penting
- **GitHub Repository**: [https://github.com/arfabi/dolanjogja](https://github.com/arfabi/dolanjogja)
- **Live Web App**: [https://dolanjogja.arfabi.id](https://dolanjogja.arfabi.id)
