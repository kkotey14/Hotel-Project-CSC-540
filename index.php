<?php require 'header.php'; require_once 'config.php'; ?>

<section class="hero">
  <div class="container hero-wrap hero-card">
    <div style="padding:28px">
      <h1 class="h1"><?= htmlspecialchars(HOTEL_NAME) ?></h1>
      <p class="lead" style="margin-bottom:16px"><?= htmlspecialchars(HOTEL_TAGLINE) ?></p>
      <p class="muted">Nestled along the river, where modern comfort meets coastal character. Newly redesigned rooms, curated details, and inviting spaces for private events and dining.</p>
      <div style="margin-top:18px">
        <a class="btn primary" href="rooms_list.php">Book Your Stay</a>
        <a class="btn" href="#amenities" style="margin-left:10px">Explore</a>
      </div>
    </div>
    <div>
      <img class="hero-image" src="https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg?auto=compress&cs=tinysrgb&h=900" alt="Hotel hero">
    </div>
  </div>
</section>

<section class="container">
  <div class="grid">
    <div class="span-6">
      <div class="card">
        <h2 class="h2">A Boutique Riverfront Hotel in Norwalk</h2>
        <p class="lead">We elevate the boutique experience with authenticity and approachable luxury.</p>
        <p>From down duvets and soft lighting to flat-screen TVs and thoughtfully designed bathrooms, every detail is considered to make your stay memorable.</p>
        <div style="margin-top:14px"><a class="btn" href="rooms_list.php">See Rooms & Rates</a></div>
      </div>
    </div>
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/271639/pexels-photo-271639.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Room detail">
      </div>
    </div>
  </div>
</section>

<section id="amenities" class="container">
  <div class="card" style="padding:26px">
    <h2 class="h2" style="margin-bottom:10px">Amenities</h2>
    <p class="muted" style="margin-bottom:18px">Everything you need for a relaxed, comfortable stay.</p>
    <div class="grid">
      <?php foreach($GLOBALS['HOTEL_AMENITIES'] as $a): ?>
        <div class="span-4 amen-card">
          <div class="amen-icon"><?= $a['icon'] ?></div>
          <div class="h3"><?= htmlspecialchars($a['title']) ?></div>
          <div class="muted"><?= htmlspecialchars($a['text']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="dine" class="container">
  <div class="grid">
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/262978/pexels-photo-262978.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Dining">
      </div>
    </div>
    <div class="span-6">
      <div class="card">
        <h2 class="h2">Dining</h2>
        <p class="lead">Riverside classics, seasonal ingredients, and crafted cocktails.</p>
        <p>From handmade pastas to wood-fired pizzas, our kitchen focuses on warm hospitality and simple, beautiful flavors.</p>
        <div style="margin-top:14px"><a class="btn ghost" href="rooms_list.php">Reserve your stay</a></div>
      </div>
    </div>
  </div>
</section>

<!-- EXPLORE SECTION -->
<section id="explore" class="container">
  <div class="grid">
    <div class="span-6">
      <div class="card">
        <h2 class="h2">Explore</h2>
        <p class="lead">Minutes from beaches, galleries, and boutique shops.</p>
        <p>Discover the best of Fairfield County, then return to the calm of <?= htmlspecialchars(HOTEL_NAME) ?>.</p>
        <div style="margin-top:14px"><a class="btn primary" href="rooms_list.php">Book Now</a></div>
      </div>
    </div>
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/5371575/pexels-photo-5371575.jpeg" alt="Explore rooftop">
      </div>
    </div>
  </div>

  <div class="grid" style="margin-top:32px">
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg" alt="Poolside">
      </div>
    </div>
    <div class="span-6">
      <div class="card">
        <h2 class="h2">Relax & Refresh</h2>
        <p class="lead">Unwind by the pool or recharge in our serene spa spaces.</p>
      </div>
    </div>
  </div>

  <div class="grid" style="margin-top:32px">
    <div class="span-6">
      <div class="card">
        <h2 class="h2">Modern Comfort</h2>
        <p class="lead">Thoughtfully designed interiors that feel both elegant and inviting.</p>
      </div>
    </div>
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/277572/pexels-photo-277572.jpeg" alt="Hallway">
      </div>
    </div>
  </div>

  <div class="grid" style="margin-top:32px">
    <div class="span-6">
      <div class="hero-img-wrap">
        <img src="https://images.pexels.com/photos/2507010/pexels-photo-2507010.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Terrace view">
      </div>
    </div>
    <div class="span-6">
      <div class="card">
        <h2 class="h2">The View</h2>
        <p class="lead">Enjoy breathtaking sunsets and riverside charm just steps away.</p>
      </div>
    </div>
  </div>
</section>

<?php require 'footer.php'; ?>
