<a href="#" class="scroll-indicator" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <i class="fas fa-arrow-up"></i>
</a>

<script>
    const scrollIndicator = document.querySelector('.scroll-indicator');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollIndicator.style.opacity = '1';
            scrollIndicator.style.transform = 'translateY(0)';
        } else {
            scrollIndicator.style.opacity = '0';
            scrollIndicator.style.transform = 'translateY(20px)';
        }
    });

    scrollIndicator.style.opacity = '0';
    scrollIndicator.style.transform = 'translateY(20px)';
    scrollIndicator.style.transition = 'all 0.3s ease';
</script>