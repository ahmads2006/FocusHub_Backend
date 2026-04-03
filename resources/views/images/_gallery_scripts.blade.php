@push('scripts')
<script>
window.setLazyLoad = function() {
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver((entries, o) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const img = e.target;
          if (img.dataset.src) {
              img.src = img.dataset.src;
              img.classList.remove('lazy');
              o.unobserve(img);
          }
        }
      });
    }, { rootMargin: '400px 0px', threshold: 0.1 });
    document.querySelectorAll('img.lazy').forEach(img => obs.observe(img));
  } else {
    document.querySelectorAll('img.lazy').forEach(img => { 
        if (img.dataset.src) {
            img.src = img.dataset.src; 
            img.classList.add('is-loaded');
        }
    });
  }
};

document.addEventListener('DOMContentLoaded', () => window.setLazyLoad());

document.addEventListener('alpine:init', () => {
  // Pre-load liked image IDs from server for instant correct initial state
  const initialLikedIds = new Set(@json($likedImageIds ?? []));
  const initialBookmarkedIds = new Set(@json($bookmarkedImageIds ?? []));

  Alpine.data('galleryPage', () => ({
    view: 'grid',
    loading: false,
    likedIds: new Set(initialLikedIds),
    bookmarkedIds: new Set(initialBookmarkedIds),
    localCounts: {},
    selectedImage: null,
    showModal: false,
    isFollowing: false,
    
    // Infinite Scroll State
    hasMore: {{ $images->hasMorePages() ? 'true' : 'false' }},
    nextPageUrl: '{!! $images->nextPageUrl() !!}',
    isLoadingMore: false,

    // Chat Share State
    showShareModal: false,
    connections: [],
    loadingConnections: false,
    sendingShareTo: null,

    init() {
      // Intersection Observer logic remains...
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMore();
                    }
                });
            }, { rootMargin: '400px' }); // Load early before reaching absolute bottom

            const observeSentinels = () => {
                document.querySelectorAll('.sentinel').forEach(el => observer.observe(el));
            };

            this.$watch('view', () => { setTimeout(observeSentinels, 100); });
            setTimeout(observeSentinels, 500);
        }
    },

    loadMore() {
        if (this.isLoadingMore || !this.hasMore || !this.nextPageUrl) return;
        this.isLoadingMore = true;

        fetch(this.nextPageUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.grid_html) {
                document.getElementById('grid-container').insertAdjacentHTML('beforeend', data.grid_html);
            }
            if (data.list_html) {
                document.getElementById('list-container').insertAdjacentHTML('beforeend', data.list_html);
            }
            
            this.hasMore = data.has_more;
            this.nextPageUrl = data.next_page_url;
            this.isLoadingMore = false;
            
            // Re-trigger global lazy loading setup
            if (window.setLazyLoad) window.setLazyLoad();
        })
        .catch(err => {
            console.error('Infinity scroll error:', err);
            this.isLoadingMore = false;
        });
    },

    dwellStartTime: null,

    openModal(data) {
        this.selectedImage = data;
        this.showModal = true;
        this.checkFollowStatus(data.user.id);
        document.body.style.overflow = 'hidden';
        this.dwellStartTime = Date.now();
    },

    closeModal() {
        this.showModal = false;
        document.body.style.overflow = 'auto';

        if (this.dwellStartTime && this.selectedImage) {
            let dwellTime = Date.now() - this.dwellStartTime;
            if (dwellTime >= 3000) {
                // Send dwell event (Deep View) automatically
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    fetch(`/images/${this.selectedImage.id}/dwell`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                    }).catch(() => {});
                } catch(e) {}
            }
            this.dwellStartTime = null;
        }
    },

    isNature() {
        return this.selectedImage?.labels?.some(l => {
            const desc = (typeof l === 'string') ? l : (l.description || '');
            return ['nature', 'mountain', 'landscape', 'forest', 'water', 'sky', 'tree', 'sea', 'ocean', 'beach'].includes(desc.toLowerCase());
        });
    },

    async checkFollowStatus(userId) {
        try {
            const res = await fetch(`/connect/${userId}/status`);
            const data = await res.json();
            this.isFollowing = data.connected && data.status === 'accepted';
        } catch (e) {
            this.isFollowing = false;
        }
    },

    async toggleFollow(userId) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`/connect/${userId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                this.isFollowing = data.status === 'followed';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'bottom-end', timer: 2500, icon: 'success', title: data.status === 'followed' ? 'تمت المتابعة' : 'تم إلغاء المتابعة', showConfirmButton: false });
                }
            }
        } catch (e) {}
    },

    isLiked(id) {
      return this.likedIds.has(id);
    },

    isBookmarked(id) {
      return this.bookmarkedIds.has(id);
    },

    likeCount(id, initialCount) {
      if (this.localCounts[id] === undefined) {
        this.localCounts[id] = initialCount;
      }
      return this.localCounts[id] > 0 ? this.localCounts[id] : '';
    },

    async toggleLike(id) {
      const wasLiked = this.likedIds.has(id);

      // Optimistic Update
      if (wasLiked) {
        this.likedIds.delete(id);
        this.localCounts[id] = Math.max(0, (this.localCounts[id] || 0) - 1);
      } else {
        this.likedIds.add(id);
        this.localCounts[id] = (this.localCounts[id] || 0) + 1;
      }

      // Force Alpine to re-evaluate
      this.likedIds = new Set(this.likedIds);

      try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(`/images/${id}/like`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        });

        if (res.status === 429) {
          // Revert optimistic update
          if (wasLiked) { this.likedIds.add(id); this.localCounts[id]++; }
          else { this.likedIds.delete(id); this.localCounts[id]--; }
          this.likedIds = new Set(this.likedIds);
          if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'bottom-end', timer: 2500, icon: 'warning', title: 'مهلاً! أنت تعجب بسرعة كبيرة.', showConfirmButton: false });
          }
          return;
        }

        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        // Correct any drift after server response
        if (json.action === 'like') { this.likedIds.add(id); }
        else { this.likedIds.delete(id); }
        this.likedIds = new Set(this.likedIds);

      } catch (e) {
        console.error('Like failed:', e);
        // Revert
        if (wasLiked) { this.likedIds.add(id); this.localCounts[id]++; }
        else { this.likedIds.delete(id); this.localCounts[id]--; }
        this.likedIds = new Set(this.likedIds);
      }
    },

    async toggleBookmark(id) {
        const wasBookmarked = this.bookmarkedIds.has(id);
        
        // Optimistic Update
        if (wasBookmarked) {
            this.bookmarkedIds.delete(id);
        } else {
            this.bookmarkedIds.add(id);
        }
        this.bookmarkedIds = new Set(this.bookmarkedIds);

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`/images/${id}/bookmark`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
            });
            const json = await res.json();
            
            if (!json.success) throw new Error(json.message);
            
            if (json.action === 'bookmark') { this.bookmarkedIds.add(id); }
            else { this.bookmarkedIds.delete(id); }
            this.bookmarkedIds = new Set(this.bookmarkedIds);
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: json.message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } catch (e) {
            console.error('Bookmark failed:', e);
            // Revert
            if (wasBookmarked) { this.bookmarkedIds.add(id); }
            else { this.bookmarkedIds.delete(id); }
            this.bookmarkedIds = new Set(this.bookmarkedIds);
        }
    },

    async openShareModal() {
        this.showShareModal = true;
        if (this.connections.length === 0) {
            this.loadingConnections = true;
            try {
                const res = await fetch('/api/chat/connections', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.connections) {
                    this.connections = data.connections;
                }
            } catch (e) {
                console.error('Failed to load connections:', e);
            } finally {
                this.loadingConnections = false;
            }
        }
    },

    closeShareModal() {
        this.showShareModal = false;
    },

    async sendToPartner(partnerId) {
        if (!this.selectedImage || this.sendingShareTo) return;
        this.sendingShareTo = partnerId;

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch('/api/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({
                    receiver_id: partnerId,
                    image_id: this.selectedImage.id,
                    body: ''
                })
            });

            if (res.ok) {
                this.closeShareModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'success',
                        title: 'تم إرسال الصورة في المحادثة بنجاح!',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            } else {
                const data = await res.json();
                throw new Error(data.error || 'Failed to send image');
            }
        } catch (e) {
            console.error('Send failed:', e);
            alert(e.message || 'حدث خطأ أثناء الإرسال');
        } finally {
            this.sendingShareTo = null;
        }
    }
  }));
});
</script>
@endpush