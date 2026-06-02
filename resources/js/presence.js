document.addEventListener('alpine:init', () => {
    Alpine.store('presence', {
        onlineUsers: [],
        offlineTimes: {},
        isInitialized: false,
        now: new Date(),

        init() {
            if (this.isInitialized || !window.Echo) return;
            this.isInitialized = true;

            // Tick every 30 seconds to update timestamps automatically
            setInterval(() => {
                this.now = new Date();
            }, 30000);

            window.Echo.join('system.users')
                .here((users) => {
                    this.onlineUsers = users.map(u => Number(u.id));
                    users.forEach(u => delete this.offlineTimes[Number(u.id)]);
                })
                .joining((user) => {
                    const id = Number(user.id);
                    if (!this.onlineUsers.includes(id)) {
                        this.onlineUsers.push(id);
                    }
                    delete this.offlineTimes[id];
                })
                .leaving((user) => {
                    const id = Number(user.id);
                    this.onlineUsers = this.onlineUsers.filter(uid => uid !== id);
                    this.offlineTimes[id] = new Date();
                });
        },
        isOnline(userId) {
            return this.onlineUsers.includes(Number(userId));
        },
        getLastSeenText(userId, lastSeenDb) {
            const dynamicLastSeen = this.offlineTimes[userId];
            const lastSeen = dynamicLastSeen || lastSeenDb;

            if (!lastSeen) return 'Never active';

            let lastSeenDate;
            if (typeof lastSeen === 'string') {
                lastSeenDate = lastSeen.endsWith('Z') ? new Date(lastSeen) : new Date(lastSeen + 'Z');
            } else {
                lastSeenDate = new Date(lastSeen);
            }

            // Reference this.now to ensure Alpine reacts to the timer
            const diffMs = this.now - lastSeenDate;
            const diffMins = Math.max(0, Math.floor(diffMs / 60000));
            const diffHrs = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHrs / 24);

            if (diffMins < 1) return 'Last seen just now';
            if (diffMins < 60) return `Last seen ${diffMins}m ago`;
            if (diffHrs < 24) return `Last seen ${diffHrs}h ago`;
            return `Last seen ${diffDays}d ago`;
        }
    });
});
