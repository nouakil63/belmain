/* Keep audio understandable when a visitor starts a different clip. */
(() => {
  const videos = [...document.querySelectorAll('.belmains-gallery-video')];
  videos.forEach((current) => {
    current.addEventListener('play', () => {
      videos.forEach((other) => {
        if (other !== current && !other.paused) other.pause();
      });
    });
  });
})();
