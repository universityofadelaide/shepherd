<?php
/**
 * @file
 * Contains RoboFileDrupalDeploy.
 *
 * Defines the methods which must be implemented to be compatible with the
 * University of Adelaide build server.
 */

/**
 * Interface RoboFileDrupalDeploy.
 */
interface RoboFileDrupalDeployInterface {

  /**
   * Apply site configuration.
   */
  public function buildApplyConfig();

  /**
   * Apply updates.
   */
  public function buildApplyUpdates();

  /**
   * Set files permissions.
   */
  public function devSetFilesOwner();

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build the code base.
   */
  public function distributionBuild();

  /**
   * Install a brand new site for a given environment.
   */
  public function environmentBuild();

  /**
   * Rebuild the environment image.
   *
   * I.e. Deploy a new release.
   */
  public function environmentRebuild();

}
