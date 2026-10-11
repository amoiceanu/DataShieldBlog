<section class="admin-stats" aria-label="Statistici articole și secțiuni">
    <article class="admin-stat-card">
        <div class="admin-stat-label"><span>Total articole</span><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
        <strong>{{ number_format($stats['articles']) }}</strong>
        <small>Articole în toate secțiunile</small>
    </article>
    <article class="admin-stat-card">
        <div class="admin-stat-label"><span>Publicate / programate</span><i class="bi bi-globe2" aria-hidden="true"></i></div>
        <strong>{{ number_format($stats['published']) }}</strong>
        <small>Cu starea publicată</small>
    </article>
    <article class="admin-stat-card">
        <div class="admin-stat-label"><span>Drafturi</span><i class="bi bi-pencil-square" aria-hidden="true"></i></div>
        <strong>{{ number_format($stats['drafts']) }}</strong>
        <small>Vizibile doar în administrare</small>
    </article>
    <article class="admin-stat-card">
        <div class="admin-stat-label"><span>Total secțiuni</span><i class="bi bi-collection" aria-hidden="true"></i></div>
        <strong>{{ number_format($stats['sections']) }}</strong>
        <small>Secțiuni editoriale</small>
    </article>
</section>
