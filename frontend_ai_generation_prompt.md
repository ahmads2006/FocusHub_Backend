# Prompt - opalshot Frontend Master Generation 🚀

**كيفية الاستخدام:**  
قم بنسخ هذا الملف بالكامل، وأرسله للذكاء الاصطناعي (مثل Claude 3.5 Sonnet، ChatGPT-4o، أو أدوات توليد الكود مثل v0.dev أو Bolt.new) لبناء مشروع واجهة أمامية (Frontend) متكامل واحترافي من الصفر يتوافق مع الباك إند المتطور لـ opalshot.

---
`[انسخ من هنا 👇]`

### Context and Role
You are an Expert Frontend Engineer and UI/UX Designer. Your task is to build a modern, high-performance, and visually stunning Frontend SPA (Single Page Application) from scratch for **"opalshot"** — a secure, premium cloud photography platform powered by AI.

### Tech Stack
*   **Framework:** Vue 3 (Composition API) or React (Next.js/Vite) — *Use whichever you are strongest at.*
*   **Styling:** Tailwind CSS with a "Glassmorphic" and "Dark Mode Premium" aesthetic (dark slate/black backgrounds with Neon Gold `#D4AF37` and White accents).
*   **State Management:** Pinia (Vue) or Zustand (React).
*   **Routing:** Standard SPA routing with navigation guards (Middleware) for protected/admin routes.
*   **HTTP Client:** Axios setup with an interceptor to automatically attach `Bearer Token` from `localStorage` and handle `401 Unauthorized` globally.

### Design System & Layouts
1.  **Main Layout:** Left Sidebar Navigation (Home, Albums, Chat, Settings) + Top App Bar (Search tags, Notifications Bell, Avatar).
2.  **Admin Layout:** Distinctive dark-red/slate theme indicating administrative privileges.
3.  **UI/UX Vibe:** Pinterest-like Masonry Grids for galleries, smooth micro-animations, skeleton loaders while fetching data, and frosted glass (backdrop-blur) elements.

---

### Key Modules & Pages to Build

#### 1. Authentication Module (`/login`, `/register`, `/verify`)
*   **Fields:** Email, Password, Username.
*   **Crucial Feature:** Add prominent **"Continue with Google"** and **"Continue with Adobe"** buttons. The platform supports "Dual Identity" (seamlessly merging OAuth users with standard accounts).
*   **Verification:** Provide a UI state for entering a 6-digit OTP code (3-minute expiry countdown).

#### 2. The Main Feed & Gallery (`/feed`)
*   **Layout:** Pinterest-style Masonry Grid.
*   **AI Tag Filters:** A horizontal scrollable chip bar with AI-generated tags (e.g., `🌿 Nature`, `🌊 Sea`, `🏙️ Urban`, `👤 Portrait`). Clicking a chip filters the feed.
*   **Content Moderation UI:** If an image object returns `is_sensitive: true` (Yellow Zone), render a **Heavy Gaussian Blur** over the image with a prominent "⚠ Warning: Sensitive Content" button that the user must click to view.
*   **Interactions:** Hovering over an image reveals Like (❤️), Bookmark (🔖) buttons, and the AI `quality_grade` (e.g., "High Quality").

#### 3. Collaborative Albums Module (`/albums`)
*   **Note:** This fully replaces traditional "Groups".
*   **UI:** Display folders. Clicking a folder opens `AlbumView`.
*   **Features:** Within an album, add a "Manage Collaborators" button. Users can search and invite other photographers to upload to the same album.
*   **Upload Button (FAB):** A pulsing Floating Action Button anywhere in the app to drag-and-drop massive image batches, displaying a progress bar overlay.

#### 4. Settings & Dynamic Watermarking (`/profile/settings`)
*   **Standard Settings:** Update Avatar, Username, Password.
*   **Unique Feature - Photography Settings:** Create a dedicated panel for "Watermark Branding".
    *   **Controls:** A toggle switch for `Dynamic Watermarking`, an input for text (e.g., "Photographer Name"), a hex color picker, an opacity slider, and a "Neon Glow" toggle.
    *   **Preview:** A live preview box that applies these styles dynamically to a sample image using CSS to simulate ImageKit's text overlay.

#### 5. Real-time Support Chat (`/chat`)
*   **Note:** This fully replaces traditional "Ticketing Systems".
*   **UI Layout:** Left pane: Contact list (followers/admins). Right pane: iMessage/WhatsApp style chat bubbles.
*   **Status:** Show green dots for online users (status fetched via API polling or WebSocket).

#### 6. Super Admin Dashboard (`/admin/dashboard`)
*   **Requirement:** NO HARDCODED DATA. Design it to map closely to live API responses.
*   **Moderation Queue:** A split-screen UI showing "Pending Review (Yellow Zone)" vs "Quarantined (Red Zone)" images. Admins have large "Approve ✔" and "Reject 🚫" buttons.
*   **User Management:** A table listing UUIDs, names, storage usage bars (e.g., 2GB / 5GB used). Contains actions to "Ban", "Unban", or toggle "Shadow Hidden".
*   **Live Logs:** A terminal-like scrolling box displaying raw system activities.

### API Integration Rules
*   All endpoints begin with `/api/v1/`.
*   ID's are `UUID v4` strings, not integers.
*   Assume the backend protects against N+1 queries and handles pagination natively; structure the UI to respect `meta.current_page` and `meta.last_page`.

Please write the foundational code for this SPA, prioritizing the **Axios Setup**, **Router configuration**, the **Main Feed (Masonry + AI Tags)**, and the **Watermark Settings UI**.

`[نهاية النسخ 👆]`
