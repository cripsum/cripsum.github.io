<?php if ($data && isset($data['data'])): 
                                    $user = $data['data']['discord_user'];
                                    $status = $data['data']['discord_status'];
                                    $activities = $data['data']['activities'];
                                ?>
                                    <p><strong><?php echo htmlspecialchars($user['username']); ?></strong> è <span style="text-transform:uppercase;"><?php echo $status; ?></span></p>

                                    <?php if (count($activities) > 0): ?>
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="activity-box">
                                                <?php
                                                    $icon = null;
                                                    if (isset($activity['assets']['large_image'])) {
                                                        $key = $activity['assets']['large_image'];
                                                        if (str_starts_with($key, 'mp:external/')) {
                                                            $icon = str_replace('mp:', 'https://media.discordapp.net/', $key);
                                                        } else {
                                                            $icon = "https://cdn.discordapp.com/app-assets/{$activity['application_id']}/$key.png";
                                                        }
                                                    }
                                                ?>
                                                <?php if ($icon): ?>
                                                    <img src="<?php echo $icon; ?>" alt="icon" class="activity-icon">
                                                <?php endif; ?>

                                                <div class="activity-info">
                                                    <p><strong><?php echo htmlspecialchars($activity['name']); ?></strong></p>
                                                    <?php if (!empty($activity['details'])): ?>
                                                        <p><?php echo htmlspecialchars($activity['details']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($activity['state'])): ?>
                                                        <p><?php echo htmlspecialchars($activity['state']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>Nessuna attività attiva</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p>Impossibile recuperare lo stato Discord.</p>
                                <?php endif; ?>