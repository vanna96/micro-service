from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from database import get_db
from controllers.category import create_category, get_categories, get_category, update_category, delete_category
from schemas.category import CategoryCreate, CategoryUpdate, Category
from fastapi import Depends


# Define individual route groups
category_route = APIRouter(
    prefix="/category",
    tags=["Category"]
)

@category_route.get("/", response_model=list[Category])
def list_categories_endpoint(skip: int = 0, limit: int = 10, db: Session = Depends(get_db)):
    return get_categories(db=db, skip=skip, limit=limit)

@category_route.post("/", response_model=Category)
def create_category_endpoint(category: CategoryCreate, db: Session = Depends(get_db)):
    try:
        return create_category(category, db)
    except HTTPException as e:
        raise e

@category_route.get("/{category_id}", response_model=Category)
def get_category_endpoint(category_id: int, db: Session = Depends(get_db)):
    return get_category(db=db, category_id=category_id)

@category_route.put("/{category_id}", response_model=Category)
def update_category_endpoint(category_id: int, category: CategoryUpdate, db: Session = Depends(get_db)):
    try:
        return update_category(db=db, category_id=category_id, category=category)
    except HTTPException as e:
        raise e

@category_route.delete("/{category_id}", response_model=Category)
def delete_category_endpoint(category_id: int, db: Session = Depends(get_db)):
    return delete_category(db=db, category_id=category_id)

# Create a parent router if needed
router = APIRouter()
router.include_router(category_route) 