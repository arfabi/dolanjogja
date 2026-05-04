<div align="center">

# 🗺️ DolanJogja
### Jogja Smart Travel Assistant — Powered by Microsoft Azure AI

**Platform panduan wisata berbasis AI yang menyusun itinerary cerdas, bebas zig-zag, dan merata untuk seluruh Yogyakarta.**

[![Live Demo](https://img.shields.io/badge/🌐_Live_Demo-dolanjogja.arfabi.id-2563EB?style=for-the-badge)](https://dolanjogja.arfabi.id)
[![Azure OpenAI](https://img.shields.io/badge/Azure_OpenAI-GPT--4o-0078D4?style=for-the-badge&logo=microsoft-azure)](https://azure.microsoft.com/en-us/products/ai-services/openai-service)
[![PWA](https://img.shields.io/badge/PWA-Mobile_First-5A0FC8?style=for-the-badge&logo=pwa)](https://dolanjogja.arfabi.id)
[![Tutorial](https://img.shields.io/badge/▶_Tutorial-YouTube-FF0000?style=for-the-badge&logo=youtube)](https://www.youtube.com/playlist?list=PLGHe7Jpha4S3lx6zQ5ldI_9a9daWVDZpd)

</div>

---

## 📱 Tampilan Aplikasi

<div align="center">

<table>
  <tr>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/f5f54ec9-74a8-4955-8417-b5b2f047affa" width="280" alt="Form Input Perjalanan"/>
      <br/><sub><b>Form Input Preferensi Perjalanan</b></sub>
    </td>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/dd20be90-3331-4331-bfda-67a09a0a1ff0" width="280" alt="Hasil Itinerary AI"/>
      <br/><sub><b>Hasil Itinerary AI — Bebas Zig-zag</b></sub>
    </td>
  </tr>
  <tr>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/f8e5d553-dd80-4e01-ba9c-af0d1db17f44" width="280" alt="Virtual Tour Guide Chatbot"/>
      <br/><sub><b>Menu Jelajah Destinasi & Hidden Gems</b></sub>
    </td>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/8c0c0630-ed5d-47f5-886f-9730a9cfcde3" width="280" alt="Menu Jelajah Destinasi"/>
      <br/><sub><b>Menu Event — Budaya & Hiburan Jogja</b></sub>
    </td>
  </tr>
  <tr>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/4fe5cfc3-572c-438c-b9a7-e0b11b9eb676" width="280" alt="Menu Event Jogja"/>
      <br/><sub><b>Menu Trip Saya — Riwayat Itinerary</b></sub>
    </td>
    <td align="center" width="50%">
      <img src="https://github.com/user-attachments/assets/23302977-d657-415d-ac48-399adf55ecb8" width="280" alt="Menu Trip Saya"/>
      <br/><sub><b>Menu Trip Saya — Bantuan</b></sub>
    </td>
  </tr>
</table>

</div>

---

## 📖 Latar Belakang

Yogyakarta menerima **40,5 juta perjalanan wisatawan** sepanjang 2025 — meningkat 6,74% dari tahun sebelumnya (BPS DIY, 2026). Namun lebih dari **62% kunjungan terkonsentrasi** hanya di Sleman dan Kota Yogyakarta, sementara Kulon Progo, Gunungkidul, dan ratusan *hidden gems* lokal nyaris tidak tersentuh.

Wisatawan modern mengandalkan konten viral media sosial sebagai panduan utama — tanpa informasi kontekstual soal jam operasional, kepadatan, rute efisien, atau parkir. Hasilnya: rute zig-zag yang memperparah kemacetan, destinasi ikonik yang kelebihan beban, dan pelaku wisata lokal (becak, andong, warung kuliner khas) yang tidak kebagian ekosistem digital.

**DolanJogja menyelesaikan masalah ini dari hulunya** — bukan sekadar aplikasi wisata, melainkan sistem distribusi wisatawan yang cerdas.

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|---|---|
| 🤖 **Itinerary Generator AI** | Menyusun jadwal harian otomatis berbasis klaster wilayah (Jogja Pusat/Utara/Selatan/Timur/Barat). Bebas rute zig-zag, mempertimbangkan jam operasional & kondisi cuaca |
| 💬 **Virtual Tour Guide Chatbot** | Asisten percakapan AI 24/7 (Bahasa Indonesia & Inggris) untuk tanya jawab seputar wisata Jogja secara real-time |
| 🎭 **Mood-Based Recommendation** | Memberi rekomendasi personal dari prompt natural language — *"pengen yang santai dan adem"*, *"liburan bareng anak usia 5 tahun"* |
| 💰 **Dynamic Budget Estimator** | Kalkulasi otomatis estimasi total biaya perjalanan: transportasi, tiket masuk, kuliner — sesuai gaya perjalanan (Backpacker / Mid-Range / Premium) |
| 💎 **Hidden Gems Distributor** | Mendistribusikan arus wisatawan ke destinasi alternatif yang kurang ramai, meratakan ekonomi pariwisata ke seluruh wilayah DIY |
| 🚍 **Transport Card Multimodal** | Pilihan transportasi lengkap di setiap perpindahan: Trans Jogja, Gojek/Grab (deep-link), Becak (pangkalan + tarif resmi), Andong, KRL |
| 🌦️ **Weather-Aware Planning** | Adaptasi cerdas ke destinasi *indoor* bila cuaca diprediksi hujan |
| 📷 **Destinasi Recognition via Foto** | Wisatawan memotret lokasi → AI mengidentifikasi tempat + info lengkapnya secara real-time |

---

## 🛠️ Arsitektur & Teknologi

DolanJogja dibangun di atas **Microsoft Azure** sebagai tulang punggung infrastruktur AI, dengan prinsip *right model for the right job* untuk efisiensi biaya maksimal.

```
┌─────────────────────────────────────────────────────────────┐
│                        DolanJogja                           │
│                  Progressive Web App (PWA)                  │
└─────────────┬───────────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────────────────┐
│                    CodeIgniter 4 REST API                   │
└──────┬──────────┬──────────┬──────────┬──────────┬──────────┘
       │          │          │          │          │
       ▼          ▼          ▼          ▼          ▼
  GPT-4o     GPT-4o     Azure AI   Azure     Azure Computer
 Itinerary   mini        Search    Maps       Vision
 Generator   Chatbot     (RAG)   (Routes)   (Foto Recog)
```

### Azure Services

| Service | Model / Tier | Fungsi |
|---|---|---|
| **Azure OpenAI — GPT-4o** | `gpt-4o` | Itinerary Generator, Mood Recommendation, Budget Estimator |
| **Azure OpenAI — GPT-4o mini** | `gpt-4o-mini` | Virtual Tour Guide Chatbot, Tanya AI (hemat 97% biaya vs GPT-4o) |
| **Azure AI Search** | Standard | RAG — menghubungkan database lokal ke AI, mencegah halusinasi |
| **Azure Maps** | S1 | Multi-stop route optimization, Transport Card, Parking Finder |
| **Azure Computer Vision** | Standard S1 | Identifikasi destinasi via foto real-time |
| **Azure Cognitive Services** | Free F0 | Multilingual support (ID/EN) untuk wisatawan mancanegara |

### Stack Tambahan

- **Backend:** CodeIgniter 4 (REST API + Admin Panel)
- **Frontend:** Progressive Web App (Mobile First)
- **Weather:** OpenWeather API

---

## 💡 Pendekatan RAG (Retrieval-Augmented Generation)

Setiap kali AI menyusun itinerary atau menjawab pertanyaan chatbot, sistem **tidak langsung memanggil GPT** — melainkan terlebih dahulu mencari data relevan dari database lokal melalui **Azure AI Search**, lalu menyertakan konteks tersebut ke prompt.

```
User Input
    │
    ▼
Azure AI Search ──► Database Lokal
(Cari konteks)       (Destinasi, Event,
    │                 Kuliner, Transport)
    ▼
GPT-4o / GPT-4o mini
(Generate dengan konteks terverifikasi)
    │
    ▼
Response akurat, bebas halusinasi
```

Database konten DolanJogja dirancang untuk dapat diintegrasikan dengan data resmi **Dinas Pariwisata DIY** ([visitingjogja.jogjaprov.go.id](https://visitingjogja.jogjaprov.go.id)) sebagai sumber primer terverifikasi.

---

## 🗄️ Database Konten (Dikurasi)

- **Destinasi:** Alam, Museum, Kuliner, Rekreasi, Sejarah
- **Event:** Konser Musik, Tradisi (Tari, Gamelan, Wayang), Pameran, Festival
- **Akomodasi:** Hotel, Guest House, Homestay, Villa
- **Transportasi:** Trans Jogja (rute & halte), KRL, Damri, Becak, Andong, Ojek Online

---

## 💸 Estimasi Biaya AI (MVP — 1.000 Users/Bulan)

| Model | Fungsi | Biaya/Bulan | % dari Total |
|---|---|---|---|
| GPT-4o | Itinerary Generator | $21,71 (~Rp 355.000) | 96,4% |
| GPT-4o mini | Chatbot Tanya AI | $0,81 (~Rp 13.300) | 3,6% |
| **Total** | | **$22,52 (~Rp 368.300)** | **100%** |

> **Biaya per user: ~Rp 376/bulan** — jauh di bawah rata-rata CAC aplikasi travel Indonesia.
> Menggunakan GPT-4o mini untuk chatbot menghemat **97% biaya** dibanding jika semua fungsi memakai GPT-4o.

---

## 🗺️ Roadmap Pengembangan

| Fase | Periode | Fokus | Status |
|:---:|:---:|---|:---:|
| **MVP** | Apr – Jun 2026 | Itinerary AI, Chatbot, Mood Rec, Budget Estimator, Azure Maps, Trans Jogja | ✅ Prototype |
| **V1** | Jul – Sep 2026 | Integrasi CCTV Pemda, Crowd Prediction, Weather Planner, Booking OTA (Traveloka, Tiket.com), KRL API | 🔜 Beta |
| **V2** | Des 2026 | Sistem Parkir Pemda, Dashboard Pemda, Integrasi API resmi Dinas Pariwisata DIY & sinkronisasi visitingjogja | 🔜 Go-to-Market |
| **V3** | Q2 2027 | Dashboard analitik: sebaran wisatawan, tren destinasi, data kemacetan *peak season* real-time | 🔜 Data Platform |

---

## 🚀 Cara Penggunaan

DolanJogja menggunakan prinsip **Mobile First** — dioptimalkan untuk smartphone. Akses via browser smartphone untuk pengalaman terbaik.

**1. Buka:** [https://dolanjogja.arfabi.id](https://dolanjogja.arfabi.id)

**2. Input perjalanan:**
- Tanggal kedatangan & kepulangan
- Cara tiba di Jogja (pesawat/kereta/bus)
- Jumlah peserta

**3. Pilih preferensi:**
- Jenis destinasi (Alam, Museum, Kuliner, Rekreasi, Sejarah)
- Moda transportasi
- Budget harian & gaya perjalanan

**4. AI menyusun itinerary** — dalam hitungan detik, jadwal lengkap siap: urutan destinasi efisien per klaster wilayah, estimasi waktu, pilihan kuliner, dan estimasi total biaya

**5. Tanya AI kapan saja** — Virtual Tour Guide siap menjawab pertanyaan selama perjalanan

**6. Bagikan** itinerary via WhatsApp atau Telegram dalam satu klik

📺 **Tutorial lengkap:** [YouTube Playlist](https://www.youtube.com/playlist?list=PLGHe7Jpha4S3lx6zQ5ldI_9a9daWVDZpd)

---

## 👥 Tim

| | Nama | Peran |
|---|---|---|
| 🧑‍💻 | **Ahmad Ridlo Fadlli Robbi** | Full Stack Developer — Arsitektur backend API, integrasi Azure (OpenAI, Maps, Computer Vision, AI Search), pengembangan web & mobile |
| 👩‍💼 | **Ani Wahyuni** | QA Engineer & Jogja Local Expert — Quality assurance produk, kurasi konten lokal, domain expert *hidden gems* Jogja |

---

## 🔗 Tautan Penting

| | Link |
|---|---|
| 🌐 Live App | [dolanjogja.arfabi.id](https://dolanjogja.arfabi.id) |
| 📺 Tutorial | [YouTube Playlist](https://www.youtube.com/playlist?list=PLGHe7Jpha4S3lx6zQ5ldI_9a9daWVDZpd) |
| 📄 Project Brief | [Microsoft Elevate Training Center — AI Impact Challenge](https://www.dicoding.com/challenges/971) |

---

## 🏆 Konteks Proyek

DolanJogja dikembangkan sebagai submission untuk **AI Impact Challenge** — kompetisi inovasi berbasis AI yang diselenggarakan oleh **Dicoding**, **Microsoft**, dan **Komdigi** dalam program **Microsoft Elevate Training Center**.

**Tema:** Digitalisasi Layanan Publik & Pariwisata

---

<div align="center">

Made with ❤️ for Yogyakarta

*"Dari 3 jam perencanaan menjadi kurang dari 2 menit."*

</div>
