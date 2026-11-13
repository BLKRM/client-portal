<?php
/**
 * Portal footer partial.
 *
 * @package WPAgencyHub
 */

defined( 'ABSPATH' ) || exit;
?>
		</div><!-- .wpah-container -->
	</main>

	<footer class="wpah-footer">
		<div class="wpah-container">
			<p class="wpah-footer__text">&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
		</div>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
