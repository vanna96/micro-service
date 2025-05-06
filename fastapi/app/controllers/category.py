from sqlalchemy.orm import Session
from fastapi import HTTPException
from models.category import Category
from schemas.category import CategoryCreate, CategoryUpdate
import base64
from io import BytesIO
from PIL import Image
import os
import uuid
from typing import Optional
from mimetypes import guess_extension
from dotenv import load_dotenv
from datetime import datetime

# Path to store the images (make sure this folder exists or create it)
# load_dotenv()
IMAGE_DIR = "assets/images/categories/"
HOST_URL = os.getenv("BACKEND_URL") 



def validate_parent_id(db: Session, parent_id: Optional[int]):
    if parent_id is not None:
        parent = db.query(Category).filter(Category.id == parent_id).first()
        if not parent:
            raise HTTPException(status_code=404, detail="Parent category not found")
            


def create_category(category: CategoryCreate, db: Session):
    # Validate the parent_id if provided
    validate_parent_id(db, category.parent_id)

    image_filename = None
    if category.image:
        image_filename = save_image_from_base64(category.image)

    # Check for name uniqueness
    existing = db.query(Category).filter(Category.name == category.name).first()
    if existing:
        raise HTTPException(status_code=400, detail="Name must be unique")

    # Create category instance
    new_category = Category(
        name=category.name,
        foreign_name=category.foreign_name,
        parent_id=category.parent_id,
        status=category.status,
        image=image_filename  # Save the filename or None
    )

    # Add and commit to the database
    try:
        db.add(new_category)
        db.commit()
        db.refresh(new_category)
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=400, detail=str(e))


    if new_category.image:
        # Assuming image filename is stored as 'image_filename' or something similar in your Category model
        image_path = os.path.join(IMAGE_DIR, new_category.image)
        # Assuming you are serving images from a public URL, you can generate the full URL like this
        new_category.image = f"{HOST_URL}/{image_path}"

    return new_category



def get_categories(db: Session, skip: int = 0, limit: int = 10, include_deleted=True):
    categories = db.query(Category).offset(skip).limit(limit).all()
    
    # Create image URL for each category
    for category in categories:
        if category.image:
            # Assuming image filename is stored as 'image_filename' or something similar in your Category model
            image_path = os.path.join(IMAGE_DIR, category.image)
            # Assuming you are serving images from a public URL, you can generate the full URL like this
            category.image = f"{HOST_URL}/{image_path}"
        else:
            category.image = None  # Or some default image URL
    
    return categories



def get_category(db: Session, category_id: int):
    category = db.query(Category).filter(Category.id == category_id).first()
    if not category:
        raise HTTPException(status_code=404, detail="Category not found")
    
    if category.image:
        # Assuming image filename is stored as 'image_filename' or something similar in your Category model
        image_path = os.path.join(IMAGE_DIR, category.image)
        # Assuming you are serving images from a public URL, you can generate the full URL like this
        category.image = f"{HOST_URL}/{image_path}"
    else:
        category.image = None  # Or some default image URL
    return category



def update_category(db: Session, category_id: int, category: CategoryUpdate):
    # Check if the category exists
    existing_category = db.query(Category).filter(Category.id == category_id).first()
    if not existing_category:
        raise HTTPException(status_code=404, detail="Category not found")
    
    # Validate parent_id if provided
    validate_parent_id(db, category.parent_id)

    # Check for name uniqueness — allow if it's the same category
    name_conflict = db.query(Category).filter(
        Category.name == category.name,
        Category.id != category_id  # Exclude current category
    ).first()

    if name_conflict:
        raise HTTPException(status_code=400, detail="Name must be unique")

    # Handle image update (optional)
    image_filename = existing_category.image
    if category.image and category.image != existing_category.image:
        image_filename = save_image_from_base64(category.image)

    # Update fields
    existing_category.name = category.name
    existing_category.foreign_name = category.foreign_name
    existing_category.parent_id = category.parent_id
    existing_category.status = category.status
    existing_category.image = image_filename

    try:
        db.commit()
        db.refresh(existing_category)
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=400, detail=str(e))

    if existing_category.image:
        # Assuming image filename is stored as 'image_filename' or something similar in your Category model
        image_path = os.path.join(IMAGE_DIR, existing_category.image)
        # Assuming you are serving images from a public URL, you can generate the full URL like this
        existing_category.image = f"{HOST_URL}/{image_path}"
    else:
        existing_category.image = None  # Or some default image URL
    return existing_category   



def delete_category(db: Session, category_id: int):
    db_category = db.query(Category).filter(Category.id == category_id).first()
    if db_category: 
        db_category.deleted_at = datetime.datetime.utcnow()
        db.commit()
        db.refresh(db_category) 
        return db_category
    else:
        raise HTTPException(status_code=404, detail="Category not found")



# Helper function to save the image from base64 string
def save_image_from_base64(base64_string: str):
    # Check if the directory exists, if not, create it
    if not os.path.exists(IMAGE_DIR):
        os.makedirs(IMAGE_DIR)

    # Decode the base64 string
    image_data = base64.b64decode(base64_string)
    # Generate a unique filename for the image
    filename = f"{uuid.uuid4().hex}.png"
    image_path = os.path.join(IMAGE_DIR, filename)

    # Save the image
    with open(image_path, "wb") as image_file:
        image_file.write(image_data)

    return filename
